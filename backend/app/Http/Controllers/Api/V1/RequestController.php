<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Services\ApprovalService;
use App\Services\AuditService;
use App\Services\AttendanceProcessor;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/* Pengajuan: cuti, izin, lembur
  Seluruh pengajuan mengikuti persetujuan berjenjang KADIV -> HRD */
class RequestController extends Controller
{
    public function __construct(
        protected ApprovalService $approval,
        protected NotificationService $notifier,
        protected AttendanceProcessor $processor,
    ) {}

    // Cuti

    public function leaveRequests(Request $request): JsonResponse
    {
        $employeeIds = $this->employeesQuery()->pluck('id');

        $query = LeaveRequest::query()
            ->with(['employee:id,nip,full_name,division_id', 'leaveType'])
            ->whereIn('employee_id', $employeeIds)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('start_date'), fn ($q, $d) => $q->where('start_date', '>=', $d))
            ->when($request->query('end_date'), fn ($q, $d) => $q->where('end_date', '<=', $d));

        return $this->paginated($query->orderByDesc('start_date')->paginate($request->integer('per_page', 15)));
    }

    public function storeLeaveRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'nullable|integer|exists:employees,id',
            'leave_type_id' => 'required|integer|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        $employeeId = $data['employee_id'] ?? $request->user()->employee_id;
        abort_if(! $employeeId, 422, 'Akun tidak terhubung dengan data karyawan.');

        $this->assertCanAccessEmployee($employeeId);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $totalDays = $start->diffInDays($end) + 1;

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        // Validasi batas waktu pengajuan
        if ($leaveType->code === 'ANNUAL' && $start->lt(now()->addDays(3)->startOfDay())) {
            return $this->unprocessable('Cuti tahunan minimal diajukan H-3 sebelum tanggal mulai.');
        }

        if ($leaveType->max_days_per_request && $totalDays > $leaveType->max_days_per_request) {
            return $this->unprocessable("Maksimal {$leaveType->max_days_per_request} hari per pengajuan.");
        }

        // Validasi kuota
        $balance = LeaveBalance::firstOrCreate(
            ['employee_id' => $employeeId, 'leave_type_id' => $leaveType->id, 'year' => now()->year],
            ['quota' => $leaveType->default_quota, 'used' => 0, 'remaining' => $leaveType->default_quota]
        );

        if ($balance->remaining < $totalDays) {
            return $this->unprocessable("Sisa kuota tidak mencukupi (tersisa {$balance->remaining} hari).");
        }

        $leave = LeaveRequest::create([
            'employee_id' => $employeeId,
            'leave_type_id' => $leaveType->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'total_days' => $totalDays,
            'reason' => $data['reason'] ?? null,
            'status' => LeaveRequest::STATUS_PENDING_KADIV,
        ]);

        AuditService::created('LEAVE', $leave, $request);
        $this->notifyKadiv($employeeId, 'Pengajuan cuti', 'cuti');

        return $this->created($leave->load('leaveType'), 'Pengajuan cuti berhasil diajukan');
    }

    public function approveLeave(Request $request, int $id): JsonResponse
    {
        $leave = LeaveRequest::findOrFail($id);
        $this->assertCanAccessEmployee($leave->employee_id);

        $leave = DB::transaction(function () use ($leave, $request) {
            $updated = $this->approval->approve($leave, $request->user());

            // Efek kuota dan attendance hanya dijalankan pada keputusan final HRD.
            if ($updated->status === LeaveRequest::STATUS_APPROVED) {
                $this->deductLeaveBalance($updated);
                $this->applyLeaveToAttendance($updated);
            }

            return $updated;
        }, 3);

        AuditService::status('LEAVE', 'APPROVE', $leave, null, $request);

        return $this->success($leave->refresh(), 'Keputusan persetujuan cuti berhasil diproses');
    }

    public function rejectLeave(Request $request, int $id): JsonResponse
    {
        $leave = LeaveRequest::findOrFail($id);
        $this->assertCanAccessEmployee($leave->employee_id);
        $note = $request->validate(['note' => 'required|string'])['note'];

        $leave = $this->approval->reject($leave, $request->user(), $note);

        AuditService::status('LEAVE', 'REJECT', $leave, $note, $request);

        return $this->success($leave, 'Pengajuan cuti ditolak');
    }

    /* GET /leave-balances */
    public function leaveBalances(Request $request): JsonResponse
    {
        $employeeIds = $this->employeesQuery()->pluck('id');

        $query = LeaveBalance::query()
            ->with(['employee:id,nip,full_name', 'leaveType'])
            ->whereIn('employee_id', $employeeIds)
            ->when($request->query('year'), fn ($q, $y) => $q->where('year', $y), fn ($q) => $q->where('year', now()->year));

        return $this->success($query->get());
    }


    // Izin

    public function permissionRequests(Request $request): JsonResponse
    {
        $employeeIds = $this->employeesQuery()->pluck('id');

        $query = PermissionRequest::query()
            ->with(['employee:id,nip,full_name'])
            ->whereIn('employee_id', $employeeIds)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('date'), fn ($q, $d) => $q->where('date', $d));

        return $this->paginated($query->orderByDesc('date')->paginate($request->integer('per_page', 15)));
    }

    public function storePermissionRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'nullable|integer|exists:employees,id',
            'type' => 'required|in:PERSONAL,SICK,OTHER',
            'date' => 'required|date',
            'start_time' => 'nullable|required_with:end_time|date_format:H:i,H:i:s',
            'end_time' => 'nullable|required_with:start_time|date_format:H:i,H:i:s',
            'reason' => 'nullable|string|max:2000',
        ]);

        $employeeId = $data['employee_id'] ?? $request->user()->employee_id;
        abort_if(! $employeeId, 422, 'Akun tidak terhubung dengan data karyawan.');

        $this->assertCanAccessEmployee($employeeId);

        $minutes = null;

        if (! empty($data['start_time']) && ! empty($data['end_time'])) {
            $permissionStart = Carbon::parse($data['date'].' '.$data['start_time'], config('app.timezone'));
            $permissionEnd = Carbon::parse($data['date'].' '.$data['end_time'], config('app.timezone'));
            if ($permissionEnd->lessThanOrEqualTo($permissionStart)) {
                $permissionEnd->addDay();
            }
            $minutes = (int) $permissionStart->diffInMinutes($permissionEnd);
            abort_if($minutes <= 0 || $minutes > 24 * 60, 422, 'Durasi izin harus lebih dari 0 dan maksimal 24 jam.');
        }

        $permission = PermissionRequest::create([
            'employee_id' => $employeeId,
            'type' => $data['type'],
            'date' => $data['date'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'duration_minutes' => $minutes,
            'reason' => $data['reason'] ?? null,
            'status' => PermissionRequest::STATUS_PENDING_KADIV,
        ]);

        AuditService::created('PERMISSION', $permission, $request);
        $this->notifyKadiv($employeeId, 'Pengajuan izin', 'izin');

        return $this->created($permission, 'Pengajuan izin berhasil diajukan');
    }

    public function approvePermission(Request $request, int $id): JsonResponse
    {
        $permission = PermissionRequest::findOrFail($id);
        $this->assertCanAccessEmployee($permission->employee_id);

        $permission = DB::transaction(function () use ($permission, $request) {
            $updated = $this->approval->approve($permission, $request->user());

            if ($updated->status === PermissionRequest::STATUS_APPROVED) {
                $this->processor->processEmployeeDate(
                    $updated->employee_id,
                    Carbon::parse($updated->date)->toDateString()
                );
            }

            return $updated;
        }, 3);

        AuditService::status('PERMISSION', 'APPROVE', $permission, null, $request);

        return $this->success($permission->refresh(), 'Keputusan persetujuan izin berhasil diproses');
    }

    public function rejectPermission(Request $request, int $id): JsonResponse
    {
        $permission = PermissionRequest::findOrFail($id);
        $this->assertCanAccessEmployee($permission->employee_id);
        $note = $request->validate(['note' => 'required|string'])['note'];

        $permission = $this->approval->reject($permission, $request->user(), $note);

        AuditService::status('PERMISSION', 'REJECT', $permission, $note, $request);

        return $this->success($permission, 'Pengajuan izin ditolak');
    }

    // Lembur

    public function overtimeRequests(Request $request): JsonResponse
    {
        $employeeIds = $this->employeesQuery()->pluck('id');

        $query = OvertimeRequest::query()
            ->with(['employee:id,nip,full_name'])
            ->whereIn('employee_id', $employeeIds)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('date'), fn ($q, $d) => $q->where('date', $d));

        return $this->paginated($query->orderByDesc('date')->paginate($request->integer('per_page', 15)));
    }

    public function storeOvertimeRequest(Request $request): JsonResponse
    {
        abort_unless($request->user()->isKadiv(), 403, 'Pengajuan lembur hanya dapat dibuat oleh KADIV.');

        $data = $request->validate([
            'employee_id' => 'nullable|integer|exists:employees,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i,H:i:s',
            'end_time' => 'required|date_format:H:i,H:i:s',
            'reason' => 'nullable|string',
        ]);

        $employeeId = $data['employee_id'] ?? $request->user()->employee_id;
        abort_if(! $employeeId, 422, 'Akun tidak terhubung dengan data karyawan.');

        $this->assertCanAccessEmployee($employeeId);

        $start = Carbon::parse($data['start_time']);
        $end = Carbon::parse($data['end_time']);
        $minutes = $start->diffInMinutes($end);

        // Validasi durasi: min 1 jam, maks 12 jam per hari
        if ($minutes < 60) {
            return $this->unprocessable('Durasi lembur minimal 1 jam.');
        }

        if ($minutes > 12 * 60) {
            return $this->unprocessable('Durasi lembur maksimal 12 jam per hari.');
        }

        // Batas pengajuan: maksimal 7 hari setelah tanggal lembur
        if (Carbon::parse($data['date'])->lt(now()->subDays(7)->startOfDay())) {
            return $this->unprocessable('Pengajuan lembur maksimal 7 hari setelah tanggal pelaksanaan.');
        }

        // Tolak pengajuan yang tumpang tindih pada tanggal sama
        $overlap = OvertimeRequest::query()
            ->where('employee_id', $employeeId)
            ->where('date', $data['date'])
            ->whereNotIn('status', [OvertimeRequest::STATUS_REJECTED])
            ->exists();

        if ($overlap) {
            return $this->unprocessable('Sudah ada pengajuan lembur pada tanggal tersebut.');
        }

        $overtime = OvertimeRequest::create([
            'employee_id' => $employeeId,
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'claimed_minutes' => $minutes,
            'reason' => $data['reason'] ?? null,
            'status' => OvertimeRequest::STATUS_PENDING_KADIV,
        ]);

        AuditService::created('OVERTIME', $overtime, $request);
        $this->notifyKadiv($employeeId, 'Pengajuan lembur', 'lembur');

        return $this->created($overtime, 'Pengajuan lembur berhasil diajukan');
    }

    public function approveOvertime(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['approved_minutes' => 'nullable|integer|min:1']);

        $overtime = OvertimeRequest::findOrFail($id);
        $this->assertCanAccessEmployee($overtime->employee_id);

        if (isset($data['approved_minutes']) && $data['approved_minutes'] > $overtime->claimed_minutes) {
            return $this->unprocessable('Durasi yang disetujui tidak boleh melebihi durasi yang diajukan.');
        }

        $overtime = DB::transaction(function () use ($overtime, $request, $data) {
            $updated = $this->approval->approve($overtime, $request->user());

            if ($updated->status === OvertimeRequest::STATUS_APPROVED) {
                $updated->forceFill([
                    'approved_minutes' => $data['approved_minutes'] ?? $updated->claimed_minutes,
                ])->save();
            }

            return $updated;
        }, 3);

        AuditService::status('OVERTIME', 'APPROVE', $overtime, null, $request);

        return $this->success($overtime->refresh(), 'Pengajuan lembur disetujui');
    }

    public function rejectOvertime(Request $request, int $id): JsonResponse
    {
        $overtime = OvertimeRequest::findOrFail($id);
        $this->assertCanAccessEmployee($overtime->employee_id);
        $note = $request->validate(['note' => 'required|string'])['note'];

        $overtime = $this->approval->reject($overtime, $request->user(), $note);

        AuditService::status('OVERTIME', 'REJECT', $overtime, $note, $request);

        return $this->success($overtime, 'Pengajuan lembur ditolak');
    }


    // Pembantu

    /* Tandai record kehadiran pada rentang cuti sebagai CUTI */
    protected function applyLeaveToAttendance(LeaveRequest $leave): void
    {
        $date = Carbon::parse($leave->start_date);
        $end = Carbon::parse($leave->end_date);

        while ($date->lte($end)) {
            $this->processor->processEmployeeDate($leave->employee_id, $date->toDateString());
            $date->addDay();
        }
    }

    protected function deductLeaveBalance(LeaveRequest $leave): void
    {
        $balance = LeaveBalance::query()
            ->where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('year', Carbon::parse($leave->start_date)->year)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $leaveType = $leave->leaveType;
            $balance = LeaveBalance::create([
                'employee_id' => $leave->employee_id,
                'leave_type_id' => $leave->leave_type_id,
                'year' => Carbon::parse($leave->start_date)->year,
                'quota' => $leaveType->default_quota,
                'used' => 0,
                'remaining' => $leaveType->default_quota,
            ]);
            $balance = LeaveBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
        }

        $days = (int) ceil((float) $leave->total_days);
        if ((float) $balance->remaining < $days) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'leave_balance' => 'Kuota cuti tidak mencukupi pada saat persetujuan final.',
            ]);
        }

        $used = (float) $balance->used + $days;
        $balance->update([
            'used' => $used,
            'remaining' => max(0, (float) $balance->quota - $used),
        ]);
    }

    protected function notifyKadiv(int $employeeId, string $title, string $jenis): void
    {
        $employee = Employee::find($employeeId);

        if (! $employee?->division_id) {
            return;
        }

        $this->notifier->notifyDivision($employee->division_id, [
            'title' => $title,
            'body' => "{$employee->full_name} mengajukan {$jenis}",
            'category' => 'LEAVE',
            'link' => '/requests',
        ]);
    }
}
