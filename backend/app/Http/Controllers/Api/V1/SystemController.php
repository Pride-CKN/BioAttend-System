<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\DeductionRecord;
use App\Models\Device;
use App\Models\Employee;
use App\Models\IntegrationLog;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\OvertimeRequest;
use App\Models\Permission;
use App\Models\PermissionRequest;
use App\Models\Report;
use App\Models\Role;
use App\Models\Setting;
use App\Models\TaxComponent;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DeductionService;
use App\Services\TaxService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Modul pendukung: dashboard, notifikasi, pengguna & role, pengaturan,
 * laporan, audit log, komponen PPh, dan perangkat.
 */
class SystemController extends Controller
{
    public function __construct(
        protected TaxService $tax,
        protected DeductionService $deductions,
    ) {}

    // ---------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------

    public function hrdSummary(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $period = now()->format('Y-m');

        return $this->success([
            'date' => $today,
            'employees' => [
                'total' => Employee::count(),
                'active' => Employee::where('employment_status', Employee::STATUS_ACTIVE)->count(),
                'inactive' => Employee::where('employment_status', '!=', Employee::STATUS_ACTIVE)->count(),
            ],
            'attendance_today' => [
                'present' => AttendanceRecord::where('work_date', $today)->where('status', AttendanceRecord::STATUS_PRESENT)->count(),
                'late' => AttendanceRecord::where('work_date', $today)->where('status', AttendanceRecord::STATUS_LATE)->count(),
                'absent' => AttendanceRecord::where('work_date', $today)->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
                'leave' => AttendanceRecord::where('work_date', $today)->where('status', AttendanceRecord::STATUS_LEAVE)->count(),
                'incomplete' => AttendanceRecord::where('work_date', $today)->where('is_incomplete', true)->count(),
            ],
            'pending_approvals' => [
                'leave' => LeaveRequest::where('status', LeaveRequest::STATUS_PENDING_KADIV)->count()
                    + LeaveRequest::where('status', LeaveRequest::STATUS_PENDING_HRD)->count(),
                'permission' => PermissionRequest::whereIn('status', ['PENDING_KADIV', 'PENDING_HRD'])->count(),
                'overtime' => OvertimeRequest::whereIn('status', ['PENDING_KADIV', 'PENDING_HRD'])->count(),
            ],
            'deduction_period' => [
                'period' => $period,
                'calculated' => DeductionRecord::where('period', $period)->where('status', 'CALCULATED')->count(),
                'confirmed' => DeductionRecord::where('period', $period)->where('status', 'CONFIRMED')->count(),
                'final' => DeductionRecord::where('period', $period)->where('status', 'FINAL')->count(),
            ],
        ]);
    }

    public function kadivSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        $employeeIds = $this->employeesQuery()->pluck('id');

        return $this->success([
            'division_id' => $user->divisionId(),
            'date' => $today,
            'team_size' => $employeeIds->count(),
            'attendance_today' => [
                'present' => AttendanceRecord::whereIn('employee_id', $employeeIds)->where('work_date', $today)->where('status', AttendanceRecord::STATUS_PRESENT)->count(),
                'late' => AttendanceRecord::whereIn('employee_id', $employeeIds)->where('work_date', $today)->where('status', AttendanceRecord::STATUS_LATE)->count(),
                'absent' => AttendanceRecord::whereIn('employee_id', $employeeIds)->where('work_date', $today)->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
            ],
            'pending_approvals' => [
                'leave' => LeaveRequest::whereIn('employee_id', $employeeIds)->where('status', LeaveRequest::STATUS_PENDING_KADIV)->count(),
                'permission' => PermissionRequest::whereIn('employee_id', $employeeIds)->where('status', 'PENDING_KADIV')->count(),
                'overtime' => OvertimeRequest::whereIn('employee_id', $employeeIds)->where('status', 'PENDING_KADIV')->count(),
            ],
        ]);
    }

    public function employeeSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user->employee_id, 403, 'Akun tidak terhubung dengan data karyawan.');

        $employeeId = $user->employee_id;
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $records = AttendanceRecord::where('employee_id', $employeeId)
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->get();

        return $this->success([
            'period' => now()->format('Y-m'),
            'summary' => [
                'present' => $records->where('status', AttendanceRecord::STATUS_PRESENT)->count(),
                'late' => $records->where('status', AttendanceRecord::STATUS_LATE)->count(),
                'absent' => $records->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
                'leave' => $records->where('status', AttendanceRecord::STATUS_LEAVE)->count(),
                'permission' => $records->where('status', AttendanceRecord::STATUS_PERMISSION)->count(),
                'total_late_minutes' => $records->sum('late_minutes'),
            ],
            'deduction' => DeductionRecord::where('employee_id', $employeeId)
                ->where('period', now()->format('Y-m'))
                ->first(),
        ]);
    }

    // ---------------------------------------------------------------
    // Notifikasi
    // ---------------------------------------------------------------

    public function notifications(Request $request): JsonResponse
    {
        $query = Notification::query()
            ->where('user_id', $request->user()->id)
            ->when($request->query('is_read') !== null, fn ($q) => $q->where('is_read', filter_var($request->query('is_read'), FILTER_VALIDATE_BOOLEAN)));

        return $this->paginated($query->orderByDesc('id')->paginate($request->integer('per_page', 20)));
    }

    public function readNotification(int $id): JsonResponse
    {
        $notification = Notification::where('user_id', auth()->id())->findOrFail($id);

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return $this->success($notification, 'Notifikasi ditandai dibaca');
    }

    public function readAllNotifications(): JsonResponse
    {
        $count = Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return $this->success(['updated' => $count], 'Semua notifikasi ditandai dibaca');
    }

    // ---------------------------------------------------------------
    // Pengguna & role
    // ---------------------------------------------------------------

    public function users(Request $request): JsonResponse
    {
        $query = User::query()->with(['roles', 'employee:id,nip,full_name'])
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($sub) => $sub
                ->where('name', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%")
            ));

        return $this->paginated($query->orderBy('name')->paginate($request->integer('per_page', 15)));
    }

    public function storeUser(Request $request): JsonResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'role' => ['required', 'in:KADIV,EMPLOYEE'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Akun HRD tidak dibuat melalui endpoint ini. HRD hanya membuat akun KADIV/Employee.
        $plainToken = \Illuminate\Support\Str::random(64);
        $expiresAt = now()->addHours((int) config('auth.activation_ttl_hours', 72));
        $temporaryPassword = \Illuminate\Support\Str::random(96);

        $user = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $plainToken, $expiresAt, $temporaryPassword) {
            $employee = Employee::query()->lockForUpdate()->findOrFail($data['employee_id']);
            abort_if($employee->employment_status !== Employee::STATUS_ACTIVE, 422, 'Akun hanya dapat dibuat untuk karyawan aktif.');
            abort_if($employee->user()->exists(), 409, 'Karyawan sudah memiliki akun pengguna.');
            abort_if($data['role'] === 'KADIV' && ! $employee->division_id, 422, 'Akun KADIV harus terhubung dengan karyawan yang memiliki divisi.');

            $user = User::create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'password' => Hash::make($temporaryPassword),
                'employee_id' => $employee->id,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'must_change_password' => true,
                'activation_token_hash' => hash('sha256', $plainToken),
                'activation_expires_at' => $expiresAt,
            ]);

            $this->syncRoles($user, [$data['role']]);

            return $user;
        }, 3);

        AuditService::created('USER', $user, $request);

        return $this->created([
            'user' => $user->load(['roles', 'employee:id,nip,full_name']),
            'activation' => [
                'token' => $plainToken,
                'expires_at' => $expiresAt->toIso8601String(),
                'delivery' => 'MANUAL_HRD',
                'note' => 'Token aktivasi hanya ditampilkan sekali. Bagikan melalui kanal internal yang aman. Provider email perlu dikonfigurasi untuk pengiriman otomatis.',
            ],
        ], 'Akun KADIV/Employee berhasil dibuat. Pengguna harus mengatur password melalui aktivasi.');
    }

    /** PATCH /system/users/{id}/status — aktivasi/nonaktif akun hanya oleh HRD. */
    public function updateUserStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $user = User::query()->with('employee')->findOrFail($id);

        abort_if($user->isHrd(), 403, 'Status akun HRD tidak dapat diubah melalui pengelolaan akun KADIV/Employee.');
        abort_if($data['is_active'] && $user->employee?->employment_status !== Employee::STATUS_ACTIVE, 422, 'Karyawan harus aktif sebelum akun login dapat diaktifkan.');

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $data): void {
            $user->forceFill(['is_active' => (bool) $data['is_active']])->save();

            if (! $data['is_active']) {
                $user->tokens()->delete();
                \App\Models\Session::query()->where('user_id', $user->id)->delete();
            }
        }, 3);

        AuditService::record('USER', $data['is_active'] ? 'ACTIVATE' : 'DEACTIVATE', 'users', $user->id, null, [
            'is_active' => (bool) $data['is_active'],
        ], $request);

        return $this->success($user->refresh()->load('roles'), $data['is_active'] ? 'Akun diaktifkan.' : 'Akun dinonaktifkan dan seluruh token dicabut.');
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'password' => \App\Support\PasswordPolicy::rules(false),
        ]);

        $user = User::findOrFail($id);
        abort_if($user->isHrd(), 403, 'Reset password melalui endpoint ini hanya untuk akun KADIV/Employee.');

        if (\App\Support\PasswordPolicy::isReused($user, $data['password'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'password' => 'Kata sandi ini pernah digunakan. Pilih kata sandi lain.',
            ]);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $data): void {
            \App\Support\PasswordPolicy::rememberCurrent($user);

            $user->forceFill([
                'password' => Hash::make($data['password']),
                'must_change_password' => true,
                'activation_token_hash' => null,
                'activation_expires_at' => null,
            ])->save();

            // Seluruh token dicabut saat reset password
            $user->tokens()->delete();
            \App\Models\Session::query()->where('user_id', $user->id)->delete();
        });

        AuditService::record('USER', 'RESET_PASSWORD', 'users', $user->id, null, null, $request);

        return $this->success(null, 'Kata sandi berhasil direset. Pemilik akun wajib menggantinya saat login berikutnya.');
    }

    public function roles(): JsonResponse
    {
        return $this->success(Role::with('permissions')->get());
    }

    public function updateRolePermissions(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role = Role::findOrFail($id);

        abort_if($role->is_system && $role->name === Role::HRD, 403, 'Permission role HRD tidak dapat diubah.');

        $role->permissions()->sync($data['permissions']);

        AuditService::record('ROLE', 'UPDATE_PERMISSIONS', 'roles', $role->id, null, ['permissions' => $data['permissions']], $request);

        return $this->success($role->load('permissions'), 'Permission berhasil diperbarui');
    }

    public function permissions(): JsonResponse
    {
        return $this->success(Permission::orderBy('module')->get());
    }

    // ---------------------------------------------------------------
    // Pengaturan
    // ---------------------------------------------------------------

    public function settings(): JsonResponse
    {
        return $this->success(Setting::orderBy('key')->get());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
            'settings.*.type' => 'in:STRING,NUMBER,BOOLEAN,JSON',
        ]);

        $updated = 0;

        foreach ($data['settings'] as $item) {
            Setting::set(
                $item['key'],
                $item['value'] ?? null,
                $item['type'] ?? Setting::TYPE_STRING
            );
            $updated++;
        }

        AuditService::record('SETTING', 'UPDATE', 'settings', null, null, ['count' => $updated], $request);

        return $this->success(['updated' => $updated], 'Pengaturan berhasil disimpan');
    }

    // ---------------------------------------------------------------
    // Laporan
    // ---------------------------------------------------------------

    public function reports(Request $request): JsonResponse
    {
        $query = Report::query()
            ->when(! auth()->user()->isHrd(), fn ($q) => $q->where('created_by', auth()->id()))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t));

        return $this->paginated($query->orderByDesc('id')->paginate($request->integer('per_page', 15)));
    }

    public function generateReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:ATTENDANCE,DEDUCTION,OVERTIME,AUDIT',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'format' => 'in:CSV,PDF,XLSX',
        ]);

        $report = Report::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'format' => $data['format'] ?? 'CSV',
            'parameters' => $data,
            'status' => Report::STATUS_DONE,
            'file_path' => null,
            'created_by' => $request->user()->id,
        ]);

        AuditService::created('REPORT', $report, $request);

        return $this->accepted($report, 'Laporan dibuat');
    }

    public function downloadReport(int $id)
    {
        $report = Report::findOrFail($id);

        abort_if(
            ! auth()->user()->isHrd() && $report->created_by !== auth()->id(),
            403,
            'Laporan berada di luar scope Anda.'
        );

        return $this->success($report, 'Laporan siap diunduh');
    }

    // ---------------------------------------------------------------
    // Audit log
    // ---------------------------------------------------------------

    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->query('module'), fn ($q, $m) => $q->where('module', $m))
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
            ->when($request->query('user_id'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->query('date_from'), fn ($q, $d) => $q->where('created_at', '>=', $d));

        return $this->paginated($query->orderByDesc('created_at')->paginate($request->integer('per_page', 50)));
    }

    public function exportAuditLogs(Request $request): JsonResponse
    {
        $logs = AuditLog::with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(10000)
            ->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Waktu', 'Pengguna', 'Role', 'Modul', 'Aksi', 'Objek', 'IP']);

        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->created_at,
                $log->user?->name,
                $log->role,
                $log->module,
                $log->action,
                $log->object_type.'#'.$log->object_id,
                $log->ip_address,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'audit-logs-'.now()->format('YmdHis').'.csv';
        Storage::disk('local')->put("exports/{$filename}", $csv);

        AuditService::record('AUDIT', 'EXPORT', 'audit_logs', null, null, ['rows' => $logs->count()], $request);

        return response()->streamDownload(fn () => print($csv), $filename, ['Content-Type' => 'text/csv']);
    }

    // ---------------------------------------------------------------
    // Komponen PPh (modul ekstensi yang dapat dimatikan)
    // ---------------------------------------------------------------

    public function taxComponents(): JsonResponse
    {
        return $this->success([
            'pph_enabled' => $this->tax->isEnabled(),
            'components' => TaxComponent::orderBy('sort_order')->get(),
        ]);
    }

    public function storeTaxComponent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:tax_components,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:DEDUCTION,ALLOWANCE',
            'calc_type' => 'required|in:PERCENTAGE,FIXED',
            'value' => 'required|numeric|min:0',
            'base_on' => 'required|in:BASE,AFTER_ATTENDANCE',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $component = TaxComponent::create($data);

        AuditService::created('TAX', $component, $request);
        $this->deductions->recalculateTax(now()->format('Y-m'), ['actor_user_id' => $request->user()->id]);

        return $this->created($component, 'Komponen PPh ditambahkan');
    }

    public function updateTaxComponent(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'in:DEDUCTION,ALLOWANCE',
            'calc_type' => 'in:PERCENTAGE,FIXED',
            'value' => 'numeric|min:0',
            'base_on' => 'in:BASE,AFTER_ATTENDANCE',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $component = TaxComponent::findOrFail($id);

        abort_if($component->is_system && ! $request->user()->isHrd(), 403, 'Komponen sistem dilindungi.');

        $old = $component->only(array_keys($data));
        $component->update($data);

        AuditService::updated('TAX', $component, $old, $request);
        $this->deductions->recalculateTax(now()->format('Y-m'), ['actor_user_id' => $request->user()->id]);

        return $this->success($component->refresh(), 'Komponen PPh diperbarui');
    }

    public function destroyTaxComponent(Request $request, int $id): JsonResponse
    {
        $component = TaxComponent::findOrFail($id);

        abort_if($component->is_system, 403, 'Komponen sistem tidak dapat dihapus.');

        $component->delete();

        AuditService::record('TAX', 'DELETE', 'tax_components', $id, null, null, $request);
        $this->deductions->recalculateTax(now()->format('Y-m'), ['actor_user_id' => $request->user()->id]);

        return $this->success(null, 'Komponen PPh dihapus');
    }

    /** PUT /tax-components/toggle — saklar global PPh. */
    public function toggleTax(Request $request): JsonResponse
    {
        $data = $request->validate(['enabled' => 'required|boolean']);

        Setting::set('PPH_ENABLED', $data['enabled'] ? '1' : '0', Setting::TYPE_BOOLEAN);

        $result = $this->deductions->recalculateTax(now()->format('Y-m'), [
            'actor_user_id' => $request->user()->id,
            'note' => 'Perubahan saklar global PPh',
        ]);

        AuditService::record('TAX', 'TOGGLE', 'settings', null, null, ['enabled' => $data['enabled']], $request);

        return $this->success([
            'pph_enabled' => $data['enabled'],
            'recalculated' => $result['updated'],
        ], $data['enabled'] ? 'Perhitungan PPh diaktifkan' : 'Perhitungan PPh dimatikan');
    }

    public function taxPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base_amount' => 'required|numeric|min:0',
            'deduction_amount' => 'nullable|numeric|min:0',
        ]);

        return $this->success(
            $this->tax->preview((float) $data['base_amount'], (float) ($data['deduction_amount'] ?? 0))
        );
    }

    // ---------------------------------------------------------------
    // Perangkat
    // ---------------------------------------------------------------
    // CATATAN: uji koneksi & sinkronisasi dipindah ke DeviceSyncController.
    // Implementasi lama (fsockopen + update last_sync_at) dihapus karena
    // melaporkan SUCCESS tanpa pernah menarik log dari perangkat. Endpoint
    // /devices/{id}/logs di bawah hanya menampilkan IntegrationLog yang
    // kini benar-benar ditulis oleh DeviceSyncService.

    public function deviceSyncLogs(int $id): JsonResponse
    {
        $logs = IntegrationLog::query()
            ->where('device_id', $id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return $this->success($logs);
    }

    // ---------------------------------------------------------------
    // Pembantu
    // ---------------------------------------------------------------

    protected function syncRoles(User $user, array $roles): void
    {
        $roleIds = Role::whereIn('name', $roles)->pluck('id');
        $user->roles()->sync($roleIds);
    }
}
