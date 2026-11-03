<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\AuditService;
use App\Services\Device\DeviceEmployeePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Session as SessionRecord;

class EmployeeController extends Controller
{
    public function __construct(protected DeviceEmployeePushService $devicePushes) {}

    /** GET /employees — HRD seluruhnya, KADIV hanya divisinya */
    public function index(Request $request): JsonResponse
    {
        $query = $this->employeesQuery()
            ->with(['department', 'division', 'position']);

        if ($q = $request->query('q')) {
            $query->where(fn ($sub) => $sub
                ->where('full_name', 'ilike', "%{$q}%")
                ->orWhere('nip', 'ilike', "%{$q}%")
                ->orWhere('email', 'ilike', "%{$q}%")
            );
        }

        if ($request->query('department_id')) {
            $query->where('department_id', $request->query('department_id'));
        }

        if ($request->query('division_id')) {
            $query->where('division_id', $request->query('division_id'));
        }

        if ($request->query('employment_status')) {
            $query->where('employment_status', $request->query('employment_status'));
        }

        return $this->paginated($query->orderBy('full_name')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nip' => 'required|string|max:30|unique:employees,nip',
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'gender' => 'nullable|in:MALE,FEMALE',
            'join_date' => 'nullable|date',
            'employment_status' => 'in:ACTIVE,INACTIVE,RESIGNED',
            'department_id' => 'nullable|exists:departments,id',
            'division_id' => 'nullable|exists:divisions,id',
            'position_id' => 'nullable|exists:positions,id',
            'manager_id' => 'nullable|integer|exists:employees,id',
        ]);

        if (! empty($data['department_id']) && ! empty($data['division_id'])) {
            abort_unless(\App\Models\Division::whereKey($data['division_id'])->where('department_id', $data['department_id'])->exists(), 422, 'Divisi tidak berada pada departemen yang dipilih.');
        }

        if (! empty($data['manager_id'])) {
            abort_if((int) $data['manager_id'] === (int) auth()->user()?->employee_id, 422, 'Karyawan tidak dapat menjadi atasannya sendiri.');
        }

        $employee = Employee::create($data);

        AuditService::created('EMPLOYEE', $employee, $request);
        if ($employee->isActive()) {
            $this->devicePushes->queueFor($employee, 'UPSERT');
        }

        return $this->created($employee->load(['department', 'division', 'position']), 'Karyawan berhasil ditambahkan');
    }

    public function show(int $id): JsonResponse
    {
        $employee = Employee::with(['department', 'division', 'position', 'user.roles'])->findOrFail($id);

        $this->assertCanAccessEmployee($employee->id);

        return $this->success($employee);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'nip' => 'sometimes|string|max:30|unique:employees,nip,'.$id,
            'full_name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'gender' => 'nullable|in:MALE,FEMALE',
            'join_date' => 'nullable|date',
            'employment_status' => 'in:ACTIVE,INACTIVE,RESIGNED',
            'department_id' => 'nullable|exists:departments,id',
            'division_id' => 'nullable|exists:divisions,id',
            'position_id' => 'nullable|exists:positions,id',
            'manager_id' => 'nullable|integer|exists:employees,id',
        ]);

        if (! empty($data['department_id']) && ! empty($data['division_id'])) {
            abort_unless(\App\Models\Division::whereKey($data['division_id'])->where('department_id', $data['department_id'])->exists(), 422, 'Divisi tidak berada pada departemen yang dipilih.');
        }

        if (array_key_exists('manager_id', $data) && $data['manager_id'] !== null) {
            abort_if((int) $data['manager_id'] === (int) $employee->id, 422, 'Karyawan tidak dapat menjadi atasannya sendiri.');
        }

        $old = $employee->only(array_keys($data));
        DB::transaction(function () use ($employee, $data): void {
            $employee->update($data);

            if (array_key_exists('employment_status', $data) && $data['employment_status'] !== Employee::STATUS_ACTIVE) {
                $user = $employee->user;
                if ($user) {
                    $user->forceFill(['is_active' => false])->save();
                    $user->tokens()->delete();
                    SessionRecord::query()->where('user_id', $user->id)->delete();
                }
            }
        }, 3);

        AuditService::updated('EMPLOYEE', $employee, $old, $request);

        $employee->refresh();
        $deviceRelevantFields = ['nip', 'full_name', 'device_user_identifier', 'employment_status'];
        if (array_intersect(array_keys($data), $deviceRelevantFields) !== []) {
            $operation = $employee->isActive() ? 'UPSERT' : 'DISABLE';
            $this->devicePushes->queueFor($employee, $operation);
        }

        return $this->success($employee->load(['department', 'division', 'position']), 'Data karyawan diperbarui');
    }

    /** DELETE /employees/{id} — BR-06: nonaktifkan, bukan hapus fisik. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        DB::transaction(function () use ($employee): void {
            $employee->update(['employment_status' => Employee::STATUS_INACTIVE]);

            $user = $employee->user;
            if ($user) {
                $user->forceFill(['is_active' => false])->save();
                $user->tokens()->delete();
                SessionRecord::query()->where('user_id', $user->id)->delete();
            }
        }, 3);

        $employee->refresh();
        $pushJobs = $this->devicePushes->queueFor($employee, 'DISABLE');

        AuditService::record('EMPLOYEE', 'DEACTIVATE', 'employees', $employee->id, null, [
            'employment_status' => Employee::STATUS_INACTIVE,
            'login_account_disabled' => (bool) $employee->user,
            'device_disable_jobs_queued' => $pushJobs,
        ], $request);

        return $this->success(null, 'Karyawan dinonaktifkan. Akun login dan seluruh token terkait juga dinonaktifkan; permintaan penonaktifan perangkat dijadwalkan bila SDK mendukungnya. Riwayat historis tetap disimpan.');
    }

    /** POST /employees/import — impor massal, diproses lewat antrean (202). */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rows' => 'required|array|min:1|max:1000',
            'rows.*.nip' => 'required|string|max:30|distinct',
            'rows.*.full_name' => 'required|string|max:255',
            'rows.*.email' => 'nullable|email|max:255',
        ]);

        $createdEmployees = [];
        $skipped = 0;

        foreach ($data['rows'] as $row) {
            if (Employee::query()->where('nip', $row['nip'])->exists()) {
                $skipped++;
                continue;
            }

            try {
                $employee = DB::transaction(fn () => Employee::create(
                    $row + ['employment_status' => Employee::STATUS_ACTIVE]
                ), 3);
                $createdEmployees[] = $employee;
            } catch (\Illuminate\Database\QueryException $exception) {
                // Unique constraint tetap menjadi penjaga utama pada race import.
                // Hanya konflik NIP yang dihitung sebagai skipped; error lain dilempar.
                if (($exception->errorInfo[0] ?? $exception->getCode()) === '23505') {
                    $skipped++;
                    continue;
                }

                throw $exception;
            }
        }

        $pushJobsQueued = 0;
        foreach ($createdEmployees as $employee) {
            // Push hanya perubahan karyawan yang baru diimpor, tidak seluruh
            // karyawan pada setiap siklus sync 15 menit.
            $pushJobsQueued += $this->devicePushes->queueFor($employee, 'UPSERT');
        }

        AuditService::record('EMPLOYEE', 'IMPORT', 'employees', null, null, [
            'created' => count($createdEmployees),
            'skipped' => $skipped,
            'device_push_jobs_queued' => $pushJobsQueued,
        ], $request);

        return $this->accepted([
            'created' => count($createdEmployees),
            'skipped' => $skipped,
            'device_push_jobs_queued' => $pushJobsQueued,
        ], 'Impor karyawan selesai; sinkronisasi ke perangkat telah diantrikan.');
    }
}
