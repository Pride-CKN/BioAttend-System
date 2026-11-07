<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ScheduleChangeHistory;
use App\Models\WorkScheduleConfig;
use App\Services\AuditService;
use App\Services\ScheduleResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/* Konfigurasi jam kerja 3 tingkat: Perusahaan › Divisi › Perorangan
  Prioritas resolusi: EMPLOYEE › DIVISION › COMPANY */
class WorkScheduleController extends Controller
{
    public function __construct(protected ScheduleResolver $resolver) {}

    /* GET /work-schedules/company */
    public function company(): JsonResponse
    {
        $config = WorkScheduleConfig::query()
            ->with('shift')
            ->where('scope_type', WorkScheduleConfig::SCOPE_COMPANY)
            ->latest('effective_from')
            ->first();

        return $this->success($config);
    }

    /* PUT /work-schedules/company */
    public function updateCompany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'work_days' => 'nullable|array',
            'work_days.*' => 'integer|between:1,7',
            'late_tolerance_minutes' => 'nullable|integer|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'reason' => 'nullable|string',
        ]);

        $config = $this->upsertConfig(
            WorkScheduleConfig::SCOPE_COMPANY,
            null,
            $data,
            $request
        );

        return $this->success($config, 'Konfigurasi jam kerja perusahaan diperbarui');
    }

    /* GET /work-schedules/divisions/{id} */
    public function division(int $id): JsonResponse
    {
        $this->assertCanAccessDivision($id);

        $config = WorkScheduleConfig::query()
            ->with('shift')
            ->where('scope_type', WorkScheduleConfig::SCOPE_DIVISION)
            ->where('scope_id', $id)
            ->latest('effective_from')
            ->first();

        return $this->success($config);
    }

    /* PUT /work-schedules/divisions/{id} */
    public function updateDivision(Request $request, int $id): JsonResponse
    {
        $this->assertCanAccessDivision($id);

        $data = $request->validate([
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'work_days' => 'nullable|array',
            'work_days.*' => 'integer|between:1,7',
            'late_tolerance_minutes' => 'nullable|integer|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'reason' => 'nullable|string',
        ]);

        $config = $this->upsertConfig(
            WorkScheduleConfig::SCOPE_DIVISION,
            $id,
            $data,
            $request
        );

        return $this->success($config, 'Konfigurasi jam kerja divisi diperbarui');
    }

    /* GET /work-schedules/employees/{id} */
    public function employee(int $id): JsonResponse
    {
        $this->assertCanAccessEmployee($id);

        $config = WorkScheduleConfig::query()
            ->with('shift')
            ->where('scope_type', WorkScheduleConfig::SCOPE_EMPLOYEE)
            ->where('scope_id', $id)
            ->latest('effective_from')
            ->first();

        return $this->success($config);
    }

    /* PUT /work-schedules/employees/{id} */
    public function updateEmployee(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'work_days' => 'nullable|array',
            'work_days.*' => 'integer|between:1,7',
            'late_tolerance_minutes' => 'nullable|integer|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'reason' => 'nullable|string',
        ]);

        $config = $this->upsertConfig(
            WorkScheduleConfig::SCOPE_EMPLOYEE,
            $id,
            $data,
            $request
        );

        return $this->success($config, 'Konfigurasi jam kerja karyawan diperbarui');
    }

    /* GET /work-schedules/resolve?employee_id=&date=
      Menampilkan jadwal aktif beserta tingkat yang dipakai */
    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'date' => 'required|date',
        ]);

        $this->assertCanAccessEmployee($data['employee_id']);

        $employee = Employee::findOrFail($data['employee_id']);
        $resolved = $this->resolver->resolve($employee, $data['date']);

        return $this->success([
            'employee' => ['id' => $employee->id, 'full_name' => $employee->full_name],
            'date' => $resolved['date'],
            'scope' => $resolved['scope'],
            'is_holiday' => $resolved['is_holiday'],
            'is_work_day' => $resolved['is_work_day'],
            'late_tolerance_minutes' => $resolved['late_tolerance'],
            'work_days' => $resolved['work_days'],
            'shift' => $resolved['shift'],
        ]);
    }

    /* POST /work-schedules/validate — deteksi bentrok sebelum disimpan */
    public function validate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope_type' => 'required|in:COMPANY,DIVISION,EMPLOYEE',
            'scope_id' => 'nullable|integer|required_if:scope_type,DIVISION|required_if:scope_type,EMPLOYEE',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);
        $this->assertScheduleScope($data['scope_type'], $data['scope_id'] ?? null);

        $conflict = $this->resolver->detectConflict(
            $data['scope_type'],
            $data['scope_id'] ?? null,
            $data['effective_from'],
            $data['effective_to'] ?? null
        );

        if ($conflict) {
            return $this->unprocessable(
                'Rentang berlaku bentrok dengan konfigurasi yang sudah ada.',
                ['conflict' => [
                    'id' => $conflict->id,
                    'effective_from' => Carbon::parse($conflict->effective_from)->toDateString(),
                    'effective_to' => $conflict->effective_to
                    ? Carbon::parse($conflict->effective_to)->toDateString(): null,
                ]]
            );
        }

        return $this->success(['valid' => true], 'Tidak ada bentrok rentang berlaku');
    }

    /* GET /work-schedules/histories */
    public function histories(Request $request): JsonResponse
    {
        $query = ScheduleChangeHistory::query()->with('config');
        $user = $request->user();

        if ($user->isKadiv()) {
            $divisionId = $user->divisionId();
            abort_if(! $divisionId, 403, 'Akun KADIV belum terhubung ke divisi.');
            $employeeIds = Employee::query()->where('division_id', $divisionId)->select('id');
            $query->where(function ($scope) use ($divisionId, $employeeIds) {
                $scope->where(function ($division) use ($divisionId) {
                    $division->where('scope_type', WorkScheduleConfig::SCOPE_DIVISION)
                        ->where('scope_id', $divisionId);
                })->orWhere(function ($employees) use ($employeeIds) {
                    $employees->where('scope_type', WorkScheduleConfig::SCOPE_EMPLOYEE)
                        ->whereIn('scope_id', $employeeIds);
                });
            });
        } elseif (! $user->isHrd()) {
            abort(403, 'Role tidak memiliki akses ke histori konfigurasi jadwal.');
        }

        if ($request->query('scope_type')) {
            $query->where('scope_type', $request->query('scope_type'));
        }
        if ($request->query('scope_id') !== null) {
            $query->where('scope_id', $request->query('scope_id'));
        }

        return $this->paginated($query->orderByDesc('created_at')->paginate(min(100, max(1, $request->integer('per_page', 20)))));
    }


    // Penugasan jadwal harian (tabel schedules)

    /* GET /schedules — daftar penugasan jadwal */
    public function index(Request $request): JsonResponse
    {
        $employeeIds = $this->employeesQuery()->pluck('id');

        $query = Schedule::query()
            ->with(['employee:id,nip,full_name', 'shift'])
            ->whereIn('employee_id', $employeeIds)
            ->when($request->query('work_date'), fn ($q, $d) => $q->where('work_date', $d))
            ->when($request->query('start_date'), fn ($q, $d) => $q->where('work_date', '>=', $d))
            ->when($request->query('end_date'), fn ($q, $d) => $q->where('work_date', '<=', $d))
            ->when($request->query('employee_id'), fn ($q, $id) => $q->where('employee_id', $id));

        return $this->paginated($query->orderBy('work_date')->paginate($request->integer('per_page', 31)));
    }

    /* GET /schedules/my — jadwal sendiri (Employee) */
    public function my(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user->employee_id, 403, 'Akun tidak terhubung dengan data karyawan.');

        $query = Schedule::query()
            ->with('shift')
            ->where('employee_id', $user->employee_id)
            ->when($request->query('start_date'), fn ($q, $d) => $q->where('work_date', '>=', $d))
            ->when($request->query('end_date'), fn ($q, $d) => $q->where('work_date', '<=', $d));

        return $this->paginated($query->orderBy('work_date')->paginate($request->integer('per_page', 31)));
    }

    /* POST /schedules/assign — tetapkan jadwal satu karyawan */
    public function assign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'work_date' => 'required|date',
            'shift_id' => 'required|integer|exists:shifts,id',
            'notes' => 'nullable|string',
        ]);
        $this->assertCanAccessEmployee((int) $data['employee_id']);

        $schedule = DB::transaction(fn () => Schedule::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'work_date' => $data['work_date']],
            [
                'shift_id' => $data['shift_id'],
                'source' => Schedule::SOURCE_MANUAL,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]
        ));

        AuditService::created('SCHEDULE', $schedule, $request);

        return $this->created($schedule->load('shift'), 'Jadwal berhasil ditetapkan');
    }

    /* POST /schedules/bulk-assign — penugasan jadwal massal (202) */
    public function bulkAssign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_ids' => 'required|array|min:1|max:500',
            'employee_ids.*' => 'integer|distinct|exists:employees,id',
            'shift_id' => 'required|integer|exists:shifts,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'include_weekend' => 'boolean',
        ]);

        $date = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        if ($date->diffInDays($end) > 366) {
            return $this->unprocessable('Rentang bulk assign maksimal 366 hari per permintaan.');
        }

        $allowedIds = $this->employeesQuery()->whereIn('id', $data['employee_ids'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $requestedIds = array_values(array_unique(array_map('intval', $data['employee_ids'])));
        abort_if(count($allowedIds) !== count($requestedIds), 403, 'Satu atau lebih karyawan berada di luar data scope Anda.');

        $assigned = 0;
        DB::transaction(function () use ($data, $request, $date, $end, &$assigned): void {
            $cursor = $date->copy();
            while ($cursor->lte($end)) {
                $isWeekend = $cursor->dayOfWeekIso > 5;

                if (! $isWeekend || ($data['include_weekend'] ?? false)) {
                    foreach ($data['employee_ids'] as $employeeId) {
                        Schedule::updateOrCreate(
                            ['employee_id' => $employeeId, 'work_date' => $cursor->toDateString()],
                            [
                                'shift_id' => $data['shift_id'],
                                'source' => Schedule::SOURCE_MANUAL,
                                'created_by' => $request->user()->id,
                            ]
                        );
                        $assigned++;
                    }
                }
                $cursor->addDay();
            }
        }, 3);

        AuditService::record('SCHEDULE', 'BULK_ASSIGN', 'schedules', null, null, ['assigned' => $assigned], $request);

        return $this->accepted(['assigned' => $assigned], 'Penugasan jadwal massal diproses');
    }

    /* Simpan konfigurasi sekaligus catat riwayat perubahan */
    protected function upsertConfig(string $scopeType, ?int $scopeId, array $data, Request $request): WorkScheduleConfig
    {
        return DB::transaction(function () use ($scopeType, $scopeId, $data, $request): WorkScheduleConfig {
            $existing = WorkScheduleConfig::query()
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->where('effective_from', $data['effective_from'])
                ->lockForUpdate()
                ->first();

            $conflict = $this->resolver->detectConflict(
                $scopeType,
                $scopeId,
                $data['effective_from'],
                $data['effective_to'] ?? null,
                $existing?->id
            );

            if ($conflict) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'effective_from' => 'Rentang berlaku bertabrakan dengan konfigurasi aktif pada tingkat yang sama.',
                ]);
            }

            $payload = [
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'shift_id' => $data['shift_id'] ?? null,
                'work_days' => $data['work_days'] ?? null,
                'late_tolerance_minutes' => $data['late_tolerance_minutes'] ?? null,
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'is_active' => true,
                'created_by' => $request->user()->id,
            ];

            if ($existing) {
                $oldValues = $existing->only(array_keys($payload));
                $existing->update($payload);
                $config = $existing->refresh();
            } else {
                $oldValues = null;
                $config = WorkScheduleConfig::create($payload);
            }

            ScheduleChangeHistory::create([
                'work_schedule_config_id' => $config->id,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'old_values' => $oldValues,
                'new_values' => $payload,
                'changed_by' => $request->user()->id,
                'reason' => $data['reason'] ?? null,
            ]);

            AuditService::record('WORK_SCHEDULE', $existing ? 'UPDATE' : 'CREATE', 'work_schedule_configs', $config->id, $oldValues, $payload, $request);

            return $config->load('shift');
        }, 3);
    }

    protected function assertCanAccessDivision(int $divisionId): void
    {
        $user = request()->user();
        abort_if(! $user->isHrd() && ! $user->isKadiv(), 403, 'Role tidak memiliki akses ke konfigurasi divisi.');
        if ($user->isKadiv()) {
            abort_if(! $user->divisionId() || (int) $user->divisionId() !== $divisionId, 403, 'Konfigurasi berada di luar divisi Anda.');
        }
    }

    protected function assertScheduleScope(string $scopeType, ?int $scopeId): void
    {
        $user = request()->user();
        if ($scopeType === WorkScheduleConfig::SCOPE_COMPANY) {
            abort_if(! $user->isHrd(), 403, 'Hanya HRD yang dapat mengubah atau memvalidasi jadwal perusahaan.');
            return;
        }

        if ($scopeType === WorkScheduleConfig::SCOPE_DIVISION) {
            abort_if($scopeId === null, 422, 'scope_id wajib untuk jadwal divisi.');
            $this->assertCanAccessDivision($scopeId);
            return;
        }

        abort_if($scopeId === null, 422, 'scope_id wajib untuk jadwal perorangan.');
        $this->assertCanAccessEmployee($scopeId);
    }
}
