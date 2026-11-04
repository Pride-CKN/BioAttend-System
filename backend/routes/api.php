<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeductionController;
use App\Http\Controllers\Api\V1\DeviceSyncController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\MasterDataController;
use App\Http\Controllers\Api\V1\RequestController;
use App\Http\Controllers\Api\V1\SystemController;
use App\Http\Controllers\Api\V1\WorkScheduleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes 
|--------------------------------------------------------------------------
| Semua endpoint berada di bawah prefix /api/v1 dan memakai format respons
| seragam: { success, message, data, meta } atau { success, message, errors }.
|
| Middleware:
|   auth:sanctum -> butuh Bearer token 
|   role:HRD,KADIV -> pembatasan role
| Scope data: HRD = global, KADIV = divisinya, EMPLOYEE = dirinya sendiri.
*/

Route::get('/health', fn () => response()->json([
    'success'   => true,
    'message'   => 'Pride Attendance API siap.',
    'data'      => [
        'app'       => config('app.name'),
        'env'       => app()->environment(),
        'timezone'  => config('app.timezone'),
        'time'      => now()->toIso8601String(),
    ],
]));

Route::prefix('v1')->group(function () {

    /*1. Autentikasi (publik + terautentikasi)*/
    Route::prefix('auth')->group(function () {
        // 5 percobaan / 15 menit per (email + IP), didefinisikan
        // pada RateLimiter::for('login') di AppServiceProvider
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:5,1');
        // Aktivasi akun baru: token sekali pakai dari HRD, tanpa login
        Route::post('activate', [AuthController::class, 'activate'])
            ->middleware('throttle:10,1');

        Route::middleware(['auth:sanctum', 'password.changed'])->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::get('profile', [AuthController::class, 'profile']);
            Route::put('profile', [AuthController::class, 'updateProfile']);
            Route::post('change-password', [AuthController::class, 'changePassword']);

            // Manajemen sesi/token
            Route::get('sessions', [AuthController::class, 'sessions']);
            Route::delete('sessions/{id}', [AuthController::class, 'revokeSession']);
        });
    });

    /*  2. Semua endpoint terautentikasi*/
    Route::middleware(['auth:sanctum', 'password.changed'])->group(function () {

        /* Dashboard */
        Route::prefix('dashboard')->group(function () {
            Route::get('hrd', [SystemController::class, 'hrdSummary'])
                ->middleware('role:HRD');
            Route::get('kadiv', [SystemController::class, 'kadivSummary'])
                ->middleware('role:HRD,KADIV');
            Route::get('employee', [SystemController::class, 'employeeSummary'])
                ->middleware('role:HRD,KADIV,EMPLOYEE');
        });

        /*Master data (HRD)*/
        // departments, divisions, positions, shifts, holidays, leave-types, devices
        Route::prefix('master')->middleware('role:HRD')->group(function () {
            Route::get('{resource}', [MasterDataController::class, 'index']);
            Route::post('{resource}', [MasterDataController::class, 'store']);
            Route::get('{resource}/{id}', [MasterDataController::class, 'show']);
            Route::put('{resource}/{id}', [MasterDataController::class, 'update']);
            Route::delete('{resource}/{id}', [MasterDataController::class, 'destroy']);
        });

        /* Karyawan */
        Route::prefix('employees')->group(function () {
            Route::get('/', [EmployeeController::class, 'index'])
                ->middleware('role:HRD,KADIV');
            Route::post('/', [EmployeeController::class, 'store'])
                ->middleware('role:HRD');
            Route::post('import', [EmployeeController::class, 'import'])
                ->middleware('role:HRD');
            Route::get('{id}', [EmployeeController::class, 'show']);
            Route::put('{id}', [EmployeeController::class, 'update'])
                ->middleware('role:HRD');
            Route::delete('{id}', [EmployeeController::class, 'destroy'])
                ->middleware('role:HRD');   
        });

        /* Jadwal kerja */
        Route::prefix('schedules')->group(function () {
            Route::get('/', [WorkScheduleController::class, 'index'])
                ->middleware('role:HRD,KADIV');
            Route::get('my', [WorkScheduleController::class, 'my']);
            Route::post('assign', [WorkScheduleController::class, 'assign'])
                ->middleware('role:HRD,KADIV');
            Route::post('bulk-assign', [WorkScheduleController::class, 'bulkAssign'])
                ->middleware('role:HRD,KADIV');
            Route::get('resolve', [WorkScheduleController::class, 'resolve']);
            Route::post('validate', [WorkScheduleController::class, 'validate'])
                ->middleware('role:HRD,KADIV');
            Route::get('histories', [WorkScheduleController::class, 'histories'])
                ->middleware('role:HRD,KADIV');
        });

        Route::prefix('work-schedule-configs')->group(function () {
            Route::get('company', [WorkScheduleController::class, 'company']);
            Route::put('company', [WorkScheduleController::class, 'updateCompany'])
                ->middleware('role:HRD');
            Route::get('division/{id}', [WorkScheduleController::class, 'division'])
                ->middleware('role:HRD,KADIV');
            Route::put('division/{id}', [WorkScheduleController::class, 'updateDivision'])
                ->middleware('role:HRD,KADIV');
            Route::get('employee/{id}', [WorkScheduleController::class, 'employee']);
            Route::put('employee/{id}', [WorkScheduleController::class, 'updateEmployee'])
                ->middleware('role:HRD');
        });

        /* Absensi  */
        Route::prefix('attendance')->group(function () {
            Route::get('my', [AttendanceController::class, 'my']);
            Route::get('recap', [AttendanceController::class, 'recap'])
                ->middleware('role:HRD,KADIV');
            Route::get('raw-logs', [AttendanceController::class, 'rawLogs'])
                ->middleware('role:HRD');                 // append-only
            Route::post('reprocess', [AttendanceController::class, 'reprocess'])
                ->middleware('role:HRD');
            Route::post('ingest', [AttendanceController::class, 'ingest'])
                ->middleware('role:HRD');                 // dari mesin fingerprint
            Route::post('process', [AttendanceController::class, 'process'])
                ->middleware('role:HRD');
            Route::post('verify', [AttendanceController::class, 'verify'])
                ->middleware('role:HRD');

            // Log tanpa identitas karyawan — mapping manual HRD
            // Diletakkan SEBELUM route {id} agar tidak tertangkap sebagai ID
            Route::get('unmatched-logs', [DeviceSyncController::class, 'unmatchedLogs'])
                ->middleware('role:HRD');
            Route::post('unmatched-logs/{id}/map', [DeviceSyncController::class, 'mapUnmatchedLog'])
                ->middleware('role:HRD');

            // Koreksi absensi (approval berjenjang)
            Route::get('corrections', [AttendanceController::class, 'corrections']);
            Route::post('corrections', [AttendanceController::class, 'storeCorrection']);
            Route::post('corrections/{id}/approve', [AttendanceController::class, 'approveCorrection'])
                ->middleware('role:HRD,KADIV');
            Route::post('corrections/{id}/reject', [AttendanceController::class, 'rejectCorrection'])
                ->middleware('role:HRD,KADIV');

            Route::get('{id}', [AttendanceController::class, 'show']);
        });

        /* Pengajuan */
        Route::prefix('requests')->group(function () {
            // Cuti
            Route::get('leave', [RequestController::class, 'leaveRequests']);
            Route::post('leave', [RequestController::class, 'storeLeaveRequest']);
            Route::post('leave/{id}/approve', [RequestController::class, 'approveLeave'])
                ->middleware('role:HRD,KADIV');
            Route::post('leave/{id}/reject', [RequestController::class, 'rejectLeave'])
                ->middleware('role:HRD,KADIV');
            Route::get('leave-balances', [RequestController::class, 'leaveBalances']);

            // Izin
            Route::get('permission', [RequestController::class, 'permissionRequests']);
            Route::post('permission', [RequestController::class, 'storePermissionRequest']);
            Route::post('permission/{id}/approve', [RequestController::class, 'approvePermission'])
                ->middleware('role:HRD,KADIV');
            Route::post('permission/{id}/reject', [RequestController::class, 'rejectPermission'])
                ->middleware('role:HRD,KADIV');

            // Lembur
            Route::get('overtime', [RequestController::class, 'overtimeRequests']);
            Route::post('overtime', [RequestController::class, 'storeOvertimeRequest'])->middleware('role:KADIV');
            Route::post('overtime/{id}/approve', [RequestController::class, 'approveOvertime'])
                ->middleware('role:HRD,KADIV');
            Route::post('overtime/{id}/reject', [RequestController::class, 'rejectOvertime'])
                ->middleware('role:HRD,KADIV');
        });

        /* Potongan */
        // ATURAN BISNIS PENTING: ALPHA (tidak masuk tanpa keterangan)
        // langsung dikenakan 4% sejak kejadian pertama — TANPA toleransi
        Route::prefix('deductions')->group(function () {
            Route::get('/', [DeductionController::class, 'index'])
                ->middleware('role:HRD,KADIV');
            Route::get('my', [DeductionController::class, 'my']);
            Route::post('preview', [DeductionController::class, 'preview'])
                ->middleware('role:HRD,KADIV');
            Route::post('calculate', [DeductionController::class, 'calculate'])
                ->middleware('role:HRD');
            Route::get('export', [DeductionController::class, 'export'])
                ->middleware('role:HRD,KADIV');
            Route::get('{id}', [DeductionController::class, 'show']);
            Route::post('{id}/confirm', [DeductionController::class, 'confirm'])
                ->middleware('role:HRD');
            // Kontrak POST. PUT dipertahankan untuk kompatibilitas klien lama
            Route::post('{id}/nominal', [DeductionController::class, 'nominal'])
                ->middleware('role:HRD');
            Route::put('{id}/nominal', [DeductionController::class, 'nominal'])
                ->middleware('role:HRD');
            Route::get('{id}/histories', [DeductionController::class, 'histories']);
        });

        /*  Notifikasi  */
        Route::prefix('notifications')->group(function () {
            Route::get('/', [SystemController::class, 'notifications']);
            Route::post('read-all', [SystemController::class, 'readAllNotifications']);
            Route::post('{id}/read', [SystemController::class, 'readNotification']);
        });

        /* Laporan */
        Route::prefix('reports')->middleware('role:HRD,KADIV')->group(function () {
            Route::get('/', [SystemController::class, 'reports']);
            Route::post('/', [SystemController::class, 'generateReport']);
            Route::get('{id}/download', [SystemController::class, 'downloadReport']);
        });

        /*  Sistem / Admin (HRD)  */
        Route::prefix('system')->middleware('role:HRD')->group(function () {
            Route::get('users', [SystemController::class, 'users']);
            Route::post('users', [SystemController::class, 'storeUser']);
            Route::patch('users/{id}/status', [SystemController::class, 'updateUserStatus']);
            Route::post('users/{id}/reset-password', [SystemController::class, 'resetPassword']);

            Route::get('roles', [SystemController::class, 'roles']);
            Route::put('roles/{id}/permissions', [SystemController::class, 'updateRolePermissions']);
            Route::get('permissions', [SystemController::class, 'permissions']);

            Route::get('settings', [SystemController::class, 'settings']);
            Route::put('settings', [SystemController::class, 'updateSettings']);

            Route::get('audit-logs', [SystemController::class, 'auditLogs']);
            Route::get('audit-logs/export', [SystemController::class, 'exportAuditLogs']);

            // PPh/full payroll berada di luar scope fase pertama
        });

        /*  Perangkat (mesin fingerprint)  */
        // Semua aksi perangkat lewat adapter + queue;
        // tidak ada lagi "test ping TCP" atau update last_sync_at palsu
        Route::prefix('devices')->middleware('role:HRD')->group(function () {
            // Route statis didahulukan agar tidak tertangkap parameter {id
            Route::get('offline', [DeviceSyncController::class, 'offline']);
            Route::get('sync-histories', [DeviceSyncController::class, 'histories']);
            Route::post('push-employee', [DeviceSyncController::class, 'pushEmployee']);

            Route::post('{id}/test', [DeviceSyncController::class, 'testConnection']);
            Route::post('{id}/sync', [DeviceSyncController::class, 'sync']);
            Route::post('{id}/retry', [DeviceSyncController::class, 'retry']);
            Route::get('{id}/checkpoint', [DeviceSyncController::class, 'checkpoint']);
            Route::get('{id}/histories', [DeviceSyncController::class, 'deviceHistories']);
            // Kompatibilitas klien lama: log integrasi per perangkat
            Route::get('{id}/logs', [SystemController::class, 'deviceSyncLogs']);
        });

        /* AI Assistant */
        Route::prefix('ai')->group(function () {
            Route::post('chat', [AiController::class, 'chat'])->middleware('throttle:30,1');
            Route::get('conversations', [AiController::class, 'conversations']);
            Route::get('conversations/{id}/messages', [AiController::class, 'messages']);
        });
    });
});

/* Endpoint tidak ditemukan -> tetap format JSON */
Route::fallback(function (Request $request) {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint tidak ditemukan: ' . $request->method() . ' ' . $request->path(),
        'errors'  => null,
    ], 404);
});