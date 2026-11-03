<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceLog;
use App\Models\AttendanceRecord;
use App\Models\Device;
use App\Models\Employee;
use App\Services\ApprovalService;
use App\Services\AttendanceProcessor;
use App\Services\AuditService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceProcessor $processor,
        protected ApprovalService $approval,
        protected NotificationService $notifier,
    ) {}

    /** GET /attendance/recap — rekap kehadiran periode (HRD / KADIV divisi). */
    public function recap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'division_id' => 'nullable|integer|exists:divisions,id',
            'status' => 'nullable|string',
        ]);

        $employeeIds = $this->employeesQuery()
            ->when($data['employee_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->when($data['division_id'] ?? null, fn ($q, $id) => $q->where('division_id', $id))
            ->pluck('id');

        $query = AttendanceRecord::query()
            ->with(['employee:id,nip,full_name,division_id', 'employee.division:id,name', 'shift'])
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$data['start_date'], $data['end_date']])
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s));

        $records = $query->orderBy('work_date')->orderBy('employee_id')->get();

        return $this->success([
            'period' => ['start' => $data['start_date'], 'end' => $data['end_date']],
            'summary' => [
                'total_records' => $records->count(),
                'present' => $records->where('status', AttendanceRecord::STATUS_PRESENT)->count(),
                'late' => $records->where('status', AttendanceRecord::STATUS_LATE)->count(),
                'early_departure' => $records->where('status', AttendanceRecord::STATUS_EARLY_DEPARTURE)->count(),
                'absent' => $records->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
                'incomplete' => $records->where('is_incomplete', true)->count(),
                'leave' => $records->where('status', AttendanceRecord::STATUS_LEAVE)->count(),
                'permission' => $records->where('status', AttendanceRecord::STATUS_PERMISSION)->count(),
                'holiday' => $records->where('status', AttendanceRecord::STATUS_HOLIDAY)->count(),
            ],
            'records' => $records,
        ]);
    }

    /** GET /attendance/my — riwayat kehadiran sendiri (Employee, BR-10). */
    public function my(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user->employee_id, 403, 'Akun tidak terhubung dengan data karyawan.');

        $data = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = AttendanceRecord::query()
            ->with('shift')
            ->where('employee_id', $user->employee_id)
            ->when($data['start_date'] ?? null, fn ($q, $d) => $q->where('work_date', '>=', $d))
            ->when($data['end_date'] ?? null, fn ($q, $d) => $q->where('work_date', '<=', $d));

        return $this->paginated($query->orderByDesc('work_date')->paginate($request->integer('per_page', 31)));
    }

    public function show(int $id): JsonResponse
    {
        $record = AttendanceRecord::with(['employee', 'shift', 'corrections'])->findOrFail($id);

        $this->assertCanAccessEmployee($record->employee_id);

        return $this->success($record);
    }

    /** POST /attendance/verify — verifikasi record kehadiran oleh HRD. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'attendance_record_ids' => 'required|array|min:1',
            'attendance_record_ids.*' => 'integer|exists:attendance_records,id',
            'notes' => 'nullable|string',
        ]);

        $updated = AttendanceRecord::query()
            ->whereIn('id', $data['attendance_record_ids'])
            ->update([
                'is_verified' => true,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

        AuditService::record('ATTENDANCE', 'VERIFY', 'attendance_records', null, null, [
            'ids' => $data['attendance_record_ids'], 'count' => $updated,
        ], $request);

        return $this->success(['verified' => $updated], 'Record kehadiran berhasil diverifikasi');
    }

    /** GET /attendance/raw-logs — log mentah pemindaian (HRD). */
    public function rawLogs(Request $request): JsonResponse
    {
        $query = AttendanceLog::query()
            ->with(['employee:id,nip,full_name', 'device:id,name'])
            ->when($request->query('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->query('device_id'), fn ($q, $id) => $q->where('device_id', $id))
            ->when($request->query('processing_status'), fn ($q, $s) => $q->where('processing_status', $s))
            ->when($request->query('is_duplicate') !== null, fn ($q) => $q->where('is_duplicate', filter_var($request->query('is_duplicate'), FILTER_VALIDATE_BOOLEAN)))
            ->when($request->query('date'), fn ($q, $d) => $q->whereDate('scanned_at', $d));

        return $this->paginated($query->orderByDesc('scanned_at')->paginate($request->integer('per_page', 50)));
    }

    /** POST /attendance/raw-logs/reprocess — proses ulang log gagal (202). */
    public function reprocess(): JsonResponse
    {
        $count = $this->processor->reprocessFailed();

        return $this->accepted(['reprocessed' => $count], 'Proses ulang log dijalankan');
    }

    /**
     * POST /attendance/ingest — terima log pemindaian dari perangkat.
     * Dipakai job sinkronisasi perangkat maupun pengujian.
     */
    public function ingest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => 'nullable|integer|exists:devices,id',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'nip' => 'nullable|string',
            'scanned_at' => 'required|date',
            'raw_payload' => 'nullable|array',
        ]);

        $employeeId = $data['employee_id'] ?? null;

        if (! $employeeId && ! empty($data['nip'])) {
            $employeeId = Employee::where('nip', $data['nip'])->value('id');
        }

        $device = ! empty($data['device_id']) ? Device::find($data['device_id']) : null;
        $scannedAt = Carbon::parse($data['scanned_at']);

        $log = $this->processor->ingest($device, $employeeId, $scannedAt, $data['raw_payload'] ?? null);

        if ($employeeId) {
            $workDate = $this->processor->resolveWorkDate(Employee::find($employeeId), $scannedAt);
            $this->processor->processEmployeeDate($employeeId, $workDate);
        }

        return $this->success($log, 'Log pemindaian diterima', $employeeId ? 200 : 202);
    }

    /** POST /attendance/process — jalankan pemrosesan harian manual. */
    public function process(Request $request): JsonResponse
    {
        $data = $request->validate(['work_date' => 'required|date']);

        $result = $this->processor->processDateForAll($data['work_date']);
        $this->processor->flagIncomplete($data['work_date']);

        AuditService::record('ATTENDANCE', 'PROCESS', 'attendance_records', null, null, $result, $request);

        return $this->accepted($result, 'Pemrosesan kehadiran selesai');
    }

    // ---------------------------------------------------------------
    // Koreksi absensi
    // ---------------------------------------------------------------

    public function corrections(Request $request): JsonResponse
    {
        $employeeIds = $this->employeesQuery()->pluck('id');

        $query = AttendanceCorrection::query()
            ->with(['employee:id,nip,full_name', 'attendanceRecord'])
            ->whereIn('employee_id', $employeeIds)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s));

        return $this->paginated($query->orderByDesc('id')->paginate($request->integer('per_page', 15)));
    }

    public function storeCorrection(Request $request): JsonResponse
    {
        $data = $request->validate([
            'attendance_record_id' => 'required|integer|exists:attendance_records,id',
            'field' => 'required|in:check_in_at,check_out_at',
            'new_value' => 'required|string|max:255',
            'reason' => 'required|string',
        ]);

        $record = AttendanceRecord::findOrFail($data['attendance_record_id']);

        // BR-10: karyawan hanya boleh mengajukan untuk dirinya sendiri
        $this->assertCanAccessEmployee($record->employee_id);

        $correction = AttendanceCorrection::create([
            'attendance_record_id' => $record->id,
            'employee_id' => $record->employee_id,
            'field' => $data['field'],
            'old_value' => (string) $record->{$data['field']},
            'new_value' => $data['new_value'],
            'reason' => $data['reason'],
            'status' => AttendanceCorrection::STATUS_PENDING_KADIV,
        ]);

        AuditService::created('ATTENDANCE_CORRECTION', $correction, $request);

        // Notifikasi ke KADIV divisi terkait
        $employee = Employee::find($record->employee_id);

        if ($employee?->division_id) {
            $this->notifier->notifyDivision($employee->division_id, [
                'title' => 'Pengajuan koreksi absensi',
                'body' => "{$employee->full_name} mengajukan koreksi {$data['field']}",
                'category' => 'ATTENDANCE',
                'link' => '/attendance/corrections',
            ]);
        }

        return $this->created($correction, 'Pengajuan koreksi berhasil diajukan');
    }

    /** POST /attendance/corrections/{id}/approve */
    public function approveCorrection(Request $request, int $id): JsonResponse
    {
        $correction = AttendanceCorrection::findOrFail($id);
        $this->assertCanAccessEmployee($correction->employee_id);

        $correction = DB::transaction(function () use ($correction, $request) {
            $updated = $this->approval->approve($correction, $request->user());

            // Perubahan derived attendance hanya diterapkan setelah HRD memberi keputusan final.
            if ($updated->status === AttendanceCorrection::STATUS_APPROVED) {
                $record = $updated->attendanceRecord()->lockForUpdate()->first();

                if ($record && in_array($updated->field, ['check_in_at', 'check_out_at'], true)) {
                    $value = $updated->new_value !== null
                        ? Carbon::parse($updated->new_value, config('app.timezone'))
                        : null;

                    $record->forceFill([$updated->field => $value, 'source' => 'CORRECTION'])->save();
                    $this->processor->processEmployeeDate($record->employee_id, $record->work_date->toDateString());
                }
            }

            return $updated;
        }, 3);

        AuditService::status('ATTENDANCE_CORRECTION', 'APPROVE', $correction, null, $request);

        return $this->success($correction->refresh(), 'Koreksi disetujui');
    }

    /** POST /attendance/corrections/{id}/reject */
    public function rejectCorrection(Request $request, int $id): JsonResponse
    {
        $correction = AttendanceCorrection::findOrFail($id);
        $this->assertCanAccessEmployee($correction->employee_id);

        $note = $request->validate(['note' => 'required|string'])['note'];

        $correction = $this->approval->reject($correction, $request->user(), $note);

        AuditService::status('ATTENDANCE_CORRECTION', 'REJECT', $correction, $note, $request);

        return $this->success($correction, 'Koreksi ditolak');
    }
}
