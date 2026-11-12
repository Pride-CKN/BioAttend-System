<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Session as SessionRecord;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Device\DeviceEmployeePushService;
use App\Services\EmployeeCodeGeneratorService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function __construct(protected DeviceEmployeePushService $devicePushes) {}

    /* GET /employees — HRD seluruhnya, KADIV hanya divisinya */

    public function index(Request $request): JsonResponse
    {
        $query = $this->employeesQuery()
            ->with(['office', 'division', 'unit', 'position']);

        if ($q = $request->query('q')) {
            $query->where(fn ($sub) => $sub
                ->where('full_name', 'ilike', "%{$q}%")
                ->orWhere('nip', 'ilike', "%{$q}%")
                ->orWhere('employee_code', 'ilike', "%{$q}%")
                ->orWhere('email', 'ilike', "%{$q}%")
            );
        }

        if ($request->filled('office_id')) {
            $query->where('office_id', $request->query('office_id'));
        }

        if ($request->filled('division_id')) {
            $query->where('division_id', $request->query('division_id'));
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->query('unit_id'));
        }

        if ($request->filled('employment_status')) {
            $query->where(
                'employment_status',
                $request->query('employment_status')
            );
        }

        return $this->paginated(
            $query->orderBy('full_name')
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nip' => ['required', 'string', 'regex:/\A[0-9]{6}\z/', 'unique:employees,nip'],
            'device_user_identifier' => ['prohibited'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:MALE,FEMALE'],
            'join_date' => ['nullable', 'date'],
            'employment_status' => ['sometimes', 'required', 'in:ACTIVE,INACTIVE,RESIGNED'],

            'office_id' => ['required', 'integer', 'exists:offices,id'],
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],

            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],

            'effective_from' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = DB::transaction(function () use ($data): Employee {
            $employee = Employee::query()->create([
                'nip' => $data['nip'],
                'full_name' => $data['full_name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'gender' => $data['gender'] ?? null,
                'join_date' => $data['join_date'] ?? null,
                'employment_status' => $data['employment_status'] ?? Employee::STATUS_ACTIVE,
                'position_id' => $data['position_id'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
            ]);

            // Employee ID dan histori dibuat melalui service resmi.
            return app(EmployeeCodeGeneratorService::class)
                ->assignOrChange(
                    employee: $employee,
                    officeId: (int) $data['office_id'],
                    divisionId: (int) $data['division_id'],
                    unitId: isset($data['unit_id'])
                        ? (int) $data['unit_id']
                        : null,
                    reason: $data['reason'] ?? 'Pembuatan data karyawan',
                    createdBy: request()->user()?->getAuthIdentifier(),
                    effectiveFrom: $data['effective_from'] ?? now()->toDateString(),
                );
        }, 3);

        AuditService::created('EMPLOYEE', $employee, $request);

        if ($employee->isActive()) {
            $this->devicePushes->queueFor($employee, 'UPSERT');
        }

        return $this->created(
            $employee->load(['office', 'division', 'unit', 'position']),
            'Karyawan berhasil ditambahkan'
        );
    }

    public function storeWithAccount(Request $request): JsonResponse
    {
        // Email karyawan juga menjadi email login.
        if (is_string($request->input('email'))) {
            $request->merge([
                'email' => mb_strtolower(trim($request->input('email'))),
            ]);
        }

        $data = $request->validate([
            // Data karyawan
            'nip' => ['required', 'string', 'regex:/\A[0-9]{6}\z/', 'unique:employees,nip'],
            'device_user_identifier' => ['prohibited'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:employees,email',
                'unique:users,email',
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:MALE,FEMALE'],
            'join_date' => ['nullable', 'date'],

            // Penempatan
            'office_id' => ['required', 'integer', 'exists:offices,id'],
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],

            // Akun
            'role' => ['required', 'in:KADIV,EMPLOYEE'],

            // Histori Employee ID
            'effective_from' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $plainToken = Str::random(64);
        $expiresAt = now()->addHours(
            (int) config('auth.activation_ttl_hours', 72)
        );

        $temporaryPassword = Str::random(96);

        // Seluruh data utama dibuat dalam satu transaksi.
        $result = DB::transaction(function () use (
            $data,
            $request,
            $plainToken,
            $expiresAt,
            $temporaryPassword
        ): array {
            // 1. Buat data karyawan.
            $employee = Employee::query()->create([
                'nip' => $data['nip'],
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'gender' => $data['gender'] ?? null,
                'join_date' => $data['join_date'] ?? null,
                'employment_status' => Employee::STATUS_ACTIVE,
                'position_id' => $data['position_id'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
            ]);

            // Generate Employee ID dan histori resmi
            $employee = app(EmployeeCodeGeneratorService::class)
                ->assignOrChange(
                    employee: $employee,
                    officeId: (int) $data['office_id'],
                    divisionId: (int) $data['division_id'],
                    unitId: isset($data['unit_id'])
                        ? (int) $data['unit_id']
                        : null,
                    reason: $data['reason'] ?? 'Pendaftaran karyawan baru',
                    createdBy: $request->user()->id,
                    effectiveFrom: $data['effective_from']
                        ?? now()->toDateString(),
                );

            // Ambil role yang dipilih HRD
            $role = Role::query()
                ->where('name', $data['role'])
                ->firstOrFail();

            // Buat akun login dengan password sementara acak
            $user = User::query()->create([
                'employee_id' => $employee->id,
                'name' => $employee->full_name,
                'email' => $employee->email,
                'password' => Hash::make($temporaryPassword),
                'is_active' => true,
                'must_change_password' => true,
                'activation_token_hash' => hash('sha256', $plainToken),
                'activation_expires_at' => $expiresAt,
            ]);

            // Tetapkan tepat satu role
            $user->roles()->sync([$role->id]);

            return [
                'employee' => $employee,
                'user' => $user,
            ];
        }, 3);

        $employee = $result['employee'];
        $user = $result['user'];

        // Audit setelah transaksi berhasil
        AuditService::created('EMPLOYEE', $employee, $request);
        AuditService::created('USER', $user, $request);

        // Gunakan mekanisme antrean perangkat yang sudah tersedia
        if ($employee->isActive()) {
            $this->devicePushes->queueFor($employee, 'UPSERT');
        }

        return $this->created([
            'employee' => $employee->load([
                'office',
                'division',
                'unit',
                'position',
                'employeeCodeHistories',
            ]),
            'user' => $user->load([
                'roles',
                'employee:id,nip,full_name,employee_code',
            ]),
            'activation' => [
                'token' => $plainToken,
                'expires_at' => $expiresAt->toIso8601String(),
                'delivery' => 'MANUAL_HRD',
                'note' => 'Token aktivasi hanya ditampilkan sekali. Bagikan melalui kanal internal yang aman.',
            ],
        ], 'Karyawan dan akun login berhasil dibuat.');
    }

    public function show(int $id): JsonResponse
    {
        $employee = Employee::query()
            ->with([
                'office',
                'division',
                'unit',
                'position',
                'user.roles',
                'employeeCodeHistories.office',
                'employeeCodeHistories.division',
                'employeeCodeHistories.unit',
            ])
            ->findOrFail($id);

        $this->assertCanAccessEmployee($employee->id);

        return $this->success($employee);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $employee = Employee::query()->findOrFail($id);

        $data = $request->validate([
            'nip' => ['sometimes', 'required', 'string', 'regex:/\A[0-9]{6}\z/', 'unique:employees,nip,'.$id],
            'device_user_identifier' => ['prohibited'],
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'gender' => ['sometimes', 'nullable', 'in:MALE,FEMALE'],
            'join_date' => ['sometimes', 'nullable', 'date'],
            'employment_status' => ['sometimes', 'in:ACTIVE,INACTIVE,RESIGNED'],

            // Jika mengubah penempatan, ketiga kolom ini wajib dikirim
            'office_id' => ['sometimes', 'required_with:division_id,unit_id', 'integer', 'exists:offices,id'],
            'division_id' => ['sometimes', 'required_with:office_id,unit_id', 'integer', 'exists:divisions,id'],
            'unit_id' => ['sometimes', 'nullable', 'integer', 'exists:units,id'],

            'position_id' => ['sometimes', 'nullable', 'integer', 'exists:positions,id'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],

            'effective_from' => ['sometimes', 'required', 'date'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $placementKeys = ['office_id', 'division_id', 'unit_id'];

        $hasPlacementChange = array_intersect(
            $placementKeys,
            array_keys($data)
        ) !== [];

        // Penempatan harus dikirim secara lengkap
        if ($hasPlacementChange) {
            foreach ($placementKeys as $field) {
                if (! array_key_exists($field, $data)) {
                    throw ValidationException::withMessages([
                        $field => "Field {$field} wajib dikirim ketika mengubah penempatan karyawan. Gunakan null untuk unit jika tidak ada.",
                    ]);
                }
            }
        }

        // Metadata histori hanya boleh digunakan saat mengubah penempatan
        if (
            ! $hasPlacementChange
            && (
                array_key_exists('effective_from', $data)
                || array_key_exists('reason', $data)
            )
        ) {
            throw ValidationException::withMessages([
                'effective_from' => 'Tanggal efektif dan alasan hanya digunakan ketika mengubah penempatan karyawan.',
            ]);
        }

        if (
            array_key_exists('manager_id', $data)
            && $data['manager_id'] !== null
        ) {
            abort_if(
                (int) $data['manager_id'] === (int) $employee->id,
                422,
                'Karyawan tidak dapat menjadi atasannya sendiri.'
            );
        }

        $old = $employee->getAttributes();

        $updatedEmployee = DB::transaction(function () use (
            $employee,
            $data,
            $hasPlacementChange
        ): Employee {
            $lockedEmployee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($employee->id);

            // Hanya atribut dasar yang boleh diperbarui secara langsung
            $employeeFields = [
                'nip',
                'full_name',
                'email',
                'phone',
                'gender',
                'join_date',
                'employment_status',
                'position_id',
                'manager_id',
            ];

            $attributes = array_intersect_key(
                $data,
                array_flip($employeeFields)
            );

            if ($attributes !== []) {
                $lockedEmployee->fill($attributes);
                $lockedEmployee->save();
            }

            // Perubahan penempatan harus melalui generator
            if ($hasPlacementChange) {
                $lockedEmployee = app(
                    EmployeeCodeGeneratorService::class
                )->assignOrChange(
                    employee: $lockedEmployee,
                    officeId: (int) $data['office_id'],
                    divisionId: (int) $data['division_id'],
                    unitId: $data['unit_id'] !== null
                        ? (int) $data['unit_id']
                        : null,
                    reason: $data['reason'] ?? 'Perubahan penempatan karyawan',
                    createdBy: request()->user()?->getAuthIdentifier(),
                    effectiveFrom: $data['effective_from'] ?? now()->toDateString(),
                );
            }

            // Nonaktifkan akun login apabila karyawan tidak lagi aktif
            if (
                array_key_exists('employment_status', $data)
                && $data['employment_status'] !== Employee::STATUS_ACTIVE
            ) {
                /** @var User|null $user */
                $user = $lockedEmployee->user()
                    ->lockForUpdate()
                    ->first();

                if ($user) {
                    $user->forceFill(['is_active' => false])->save();
                    $user->tokens()->delete();

                    SessionRecord::query()
                        ->where('user_id', $user->id)
                        ->delete();
                }
            }

            return $lockedEmployee->fresh();
        }, 3);

        AuditService::updated(
            'EMPLOYEE',
            $updatedEmployee,
            $old,
            $request
        );

        $deviceRelevantFields = [
            'nip',
            'full_name',
            'device_user_identifier',
            'employment_status',
        ];

        if (
            array_intersect(
                array_keys($data),
                $deviceRelevantFields
            ) !== []
        ) {
            $operation = $updatedEmployee->isActive()
                ? 'UPSERT'
                : 'DISABLE';

            $this->devicePushes->queueFor(
                $updatedEmployee,
                $operation
            );
        }

        return $this->success(
            $updatedEmployee->load([
                'office',
                'division',
                'unit',
                'position',
            ]),
            'Data karyawan berhasil diperbarui.'
        );
    }

    /* DELETE /employees/{id} —  nonaktifkan, bukan hapus fisik */
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

    /* POST /employees/import — impor massal, diproses lewat antrean (202) */

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:1000'],

            'rows.*.nip' => ['required', 'string', 'regex:/\A[0-9]{6}\z/', 'distinct'],
            'rows.*.device_user_identifier' => ['prohibited'],
            'rows.*.full_name' => ['required', 'string', 'max:255'],
            'rows.*.email' => ['nullable', 'email', 'max:255'],
            'rows.*.phone' => ['nullable', 'string', 'max:30'],
            'rows.*.gender' => ['nullable', 'in:MALE,FEMALE'],
            'rows.*.join_date' => ['nullable', 'date'],
            'rows.*.employment_status' => [
                'sometimes',
                'in:ACTIVE,INACTIVE,RESIGNED',
            ],

            // Penempatan wajib disertakan untuk setiap karyawan.
            'rows.*.office_id' => [
                'required',
                'integer',
                'exists:offices,id',
            ],
            'rows.*.division_id' => [
                'required',
                'integer',
                'exists:divisions,id',
            ],
            'rows.*.unit_id' => [
                'present',
                'nullable',
                'integer',
                'exists:units,id',
            ],

            'rows.*.position_id' => [
                'nullable',
                'integer',
                'exists:positions,id',
            ],
            'rows.*.manager_id' => [
                'nullable',
                'integer',
                'exists:employees,id',
            ],
            'rows.*.effective_from' => ['nullable', 'date'],
            'rows.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        $createdEmployees = [];
        $skipped = 0;

        foreach ($data['rows'] as $row) {
            // NIP yang sudah ada tidak dibuat ulang
            if (Employee::query()->where('nip', $row['nip'])->exists()) {
                $skipped++;

                continue;
            }

            try {
                $employee = DB::transaction(function () use ($row): Employee {
                    $employee = Employee::query()->create([
                        'nip' => $row['nip'],
                        'full_name' => $row['full_name'],
                        'email' => $row['email'] ?? null,
                        'phone' => $row['phone'] ?? null,
                        'gender' => $row['gender'] ?? null,
                        'join_date' => $row['join_date'] ?? null,
                        'employment_status' => $row['employment_status']
                            ?? Employee::STATUS_ACTIVE,
                        'position_id' => $row['position_id'] ?? null,
                        'manager_id' => $row['manager_id'] ?? null,
                    ]);

                    return app(
                        EmployeeCodeGeneratorService::class
                    )->assignOrChange(
                        employee: $employee,
                        officeId: (int) $row['office_id'],
                        divisionId: (int) $row['division_id'],
                        unitId: $row['unit_id'] !== null
                            ? (int) $row['unit_id']
                            : null,
                        reason: $row['reason']
                            ?? 'Impor massal data karyawan',
                        createdBy: request()->user()?->getAuthIdentifier(),
                        effectiveFrom: $row['effective_from']
                            ?? now()->toDateString(),
                    );
                }, 3);

                $createdEmployees[] = $employee;

            } catch (QueryException $exception) {
                // Menangani konflik NIP akibat proses bersamaan
                if (($exception->errorInfo[0] ?? $exception->getCode()) === '23505') {
                    $skipped++;

                    continue;
                }

                throw $exception;
            }
        }

        $pushJobsQueued = 0;

        foreach ($createdEmployees as $employee) {
            $operation = $employee->isActive() ? 'UPSERT' : 'DISABLE';

            $pushJobsQueued += $this->devicePushes->queueFor(
                $employee,
                $operation
            );
        }

        AuditService::record(
            'EMPLOYEE',
            'IMPORT',
            'employees',
            null,
            null,
            [
                'created' => count($createdEmployees),
                'skipped' => $skipped,
                'device_push_jobs_queued' => $pushJobsQueued,
            ],
            $request
        );

        return $this->accepted([
            'created' => count($createdEmployees),
            'skipped' => $skipped,
            'device_push_jobs_queued' => $pushJobsQueued,
        ], 'Impor karyawan selesai; Employee ID dan histori penempatan telah dibuat.');
    }
}
