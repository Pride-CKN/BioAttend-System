<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MapUnmatchedLogRequest;
use App\Http\Requests\PushEmployeeToDeviceRequest;
use App\Jobs\AttendanceProcessingJob;
use App\Jobs\DeviceSyncJob;
use App\Models\AttendanceLog;
use App\Models\AttendanceLogMappingHistory;
use App\Models\Device;
use App\Enums\DeviceStatus;
use App\Models\DeviceCheckpoint;
use App\Models\DeviceSyncHistory;
use App\Models\Employee;
use App\Services\AuditService;
use App\Services\Device\DeviceEmployeePushService;
use App\Services\Device\DeviceSyncService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*Integrasi perangkat ZKTeco
 
  Endpoint di sini TIDAK melakukan koneksi LAN secara langsung. Semua
  pekerjaan perangkat didelegasikan ke DeviceSyncService / DeviceSyncJob
  sehingga status yang dikembalikan selalu mencerminkan keadaan nyata.
  Bila vendor SDK belum terpasang, hasilnya dilaporkan sebagai BLOCKED,
  bukan "berhasil" (lihat docs/ZKTECO-INTEGRATION.md) */
class DeviceSyncController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DeviceSyncService $sync,
        protected DeviceEmployeePushService $pushes,
    ) {}

    // Koneksi & sinkronisasi

    /* POST /devices/{id}/test — uji koneksi lewat adapter (bukan ping TCP) */
    public function testConnection(int $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $result = $this->sync->testConnection($device);

        $blocked = $result->errorCode === 'SDK_UNAVAILABLE';

        return $this->success([
            'device_id' => $device->id,
            'status' => $result->status,
            'connected' => $result->isConnected(),
            'device_status' => $device->refresh()->status,
            'latency_ms' => $result->latencyMs,
            'firmware' => $result->firmware,
            'error_code' => $result->errorCode,
            'message' => $result->message,
            'integration_status' => $blocked ? 'BLOCKED' : ($result->isConnected() ? 'READY' : 'FAILED'),
            'note' => $blocked
                ? 'Vendor SDK ZKTeco belum terpasang. Tidak ada koneksi perangkat nyata yang diverifikasi.'
                : null,
        ], $result->isConnected() ? 'Perangkat terhubung' : 'Perangkat tidak terhubung');
    }

    /* POST /devices/{id}/sync — dispatch job, bukan update last_sync_at palsu */
    public function sync(int $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        if (! $device->is_active) {
            return $this->conflict('Perangkat tidak aktif. Aktifkan terlebih dahulu.');
        }

        $correlationId = (string) Str::uuid();

        DeviceSyncJob::dispatch($device->id, 'manual', $correlationId)
            ->onQueue((string) config('zkteco.queue', 'device-sync'));

        return $this->accepted([
            'device_id' => $device->id,
            'correlation_id' => $correlationId,
            'queue' => (string) config('zkteco.queue', 'device-sync'),
            'status' => 'QUEUED',
            'note' => 'Sinkronisasi berjalan asynchronous. Cek /devices/{id}/histories untuk hasil nyata.',
        ], 'Sinkronisasi perangkat dimasukkan ke antrean');
    }

    /* POST /devices/{id}/retry — ulang hanya bila siklus terakhir gagal */
    public function retry(int $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $last = DeviceSyncHistory::query()
            ->where('device_id', $device->id)
            ->whereNotIn('status', [DeviceSyncHistory::STATUS_RUNNING])
            ->latest('started_at')
            ->first();

        $retryable = [
            DeviceSyncHistory::STATUS_FAILED,
            DeviceSyncHistory::STATUS_TIMEOUT,
            DeviceSyncHistory::STATUS_PARTIAL_SUCCESS,
        ];

        if (! $last || ! in_array($last->status, $retryable, true)) {
            return $this->conflict('Tidak ada siklus gagal untuk diulang.');
        }

        $correlationId = (string) Str::uuid();

        DeviceSyncJob::dispatch($device->id, 'retry', $correlationId)
            ->onQueue((string) config('zkteco.queue', 'device-sync'));

        return $this->accepted([
            'device_id' => $device->id,
            'correlation_id' => $correlationId,
            'retried_from_history_id' => $last->id,
            'previous_status' => $last->status,
            'previous_error_code' => $last->error_code,
            'status' => 'QUEUED',
        ], 'Sinkronisasi ulang dimasukkan ke antrean');
    }

    // Riwayat & kesehatan

    /* GET /devices/sync-histories — seluruh perangkat, pagination + filter */
    public function histories(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['sometimes', 'integer', 'exists:devices,id'],
            'status' => ['sometimes', 'string', 'max:30'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = DeviceSyncHistory::query()->orderByDesc('started_at');

        if (isset($validated['device_id'])) {
            $query->where('device_id', $validated['device_id']);
        }
        if (isset($validated['status'])) {
            $query->where('status', strtoupper($validated['status']));
        }
        if (isset($validated['from'])) {
            $query->where('started_at', '>=', $validated['from'].' 00:00:00');
        }
        if (isset($validated['to'])) {
            $query->where('started_at', '<=', $validated['to'].' 23:59:59');
        }

        return $this->paginated($query->paginate($validated['per_page'] ?? 20));
    }

    /* GET /devices/{id}/histories — riwayat satu perangkat */
    public function deviceHistories(int $id, Request $request): JsonResponse
    {
        Device::findOrFail($id);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', 'max:30'],
        ]);

        $query = DeviceSyncHistory::query()
            ->where('device_id', $id)
            ->orderByDesc('started_at');

        if (isset($validated['status'])) {
            $query->where('status', strtoupper($validated['status']));
        }

        return $this->paginated($query->paginate($validated['per_page'] ?? 20));
    }

    /* GET /devices/{id}/checkpoint — posisi sinkronisasi & strategi jujur */
    public function checkpoint(int $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $checkpoint = DeviceCheckpoint::query()->where('device_id', $device->id)->first();

        return $this->success([
            'device_id' => $device->id,
            'last_sync_at' => $device->last_sync_at?->toIso8601String(),
            'last_successful_connection_at' => $device->last_successful_connection_at?->toIso8601String(),
            'consecutive_failures' => $device->consecutive_failures,
            'last_error_code' => $device->last_error_code,
            'checkpoint' => $checkpoint ? [
                'cursor' => $checkpoint->cursor,
                'strategy' => $checkpoint->strategy,
                'last_scan_at' => $checkpoint->last_scan_at?->toIso8601String(),
                'last_inserted_log_id' => $checkpoint->last_inserted_log_id,
            ] : null,
            // FULL_SCAN bukan incremental — dilaporkan apa adanya.
            'incremental' => $checkpoint?->strategy === DeviceCheckpoint::STRATEGY_SDK_CURSOR,
        ]);
    }

    /* GET /devices/offline — perangkat yang perlu perhatian HRD */
    public function offline(): JsonResponse
    {
    $devices = Device::query()
        ->where('is_active', true)
        ->where('status', DeviceStatus::Offline->value)
        ->orderBy('id')
        ->get()
        ->map(static fn ($d) => [
            'id' => $d->id,
            'name' => $d->name,
            'ip_address' => $d->ip_address,
            'status' => $d->status->value,
            'consecutive_failures' => $d->consecutive_failures,
            'last_error_code' => $d->last_error_code,
            'last_error_message' => $d->last_error_message,
            'last_successful_connection_at' => $d->last_successful_connection_at?->toIso8601String(),
        ]);

    return $this->success($devices);
    }

    // Push karyawan (event-driven)

    /* POST /devices/push-employee */
    public function pushEmployee(PushEmployeeToDeviceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $employee = Employee::findOrFail($data['employee_id']);
        $operation = $data['operation'] ?? 'UPSERT';

        if ($employee->device_user_identifier === null && $operation === 'UPSERT') {
            return $this->unprocessable(
                'Karyawan belum memiliki device_user_identifier. Isi terlebih dahulu sebelum push.'
            );
        }

        $queued = $this->pushes->queueFor($employee, $operation);

        AuditService::record('DEVICE', 'PUSH_EMPLOYEE', 'employees', $employee->id, null, [
            'operation' => $operation,
            'jobs_queued' => $queued,
        ], $request);

        return $this->accepted([
            'employee_id' => $employee->id,
            'operation' => $operation,
            'jobs_queued' => $queued,
            'note' => $queued === 0
                ? 'Tidak ada perubahan data relevan atau push sudah identik (idempotent).'
                : 'Push berjalan asynchronous per perangkat.',
        ], $queued > 0 ? 'Push karyawan dijadwalkan' : 'Tidak ada push baru yang diperlukan');
    }

    // Log unmatched// 

    /* GET /attendance/unmatched-logs */
    public function unmatchedLogs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['sometimes', 'integer', 'exists:devices,id'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AttendanceLog::query()
            ->whereNull('employee_id')
            ->where('processing_status', AttendanceLog::STATUS_UNMATCHED)
            ->orderByDesc('scanned_at');

        if (isset($validated['device_id'])) {
            $query->where('device_id', $validated['device_id']);
        }
        if (isset($validated['from'])) {
            $query->where('scanned_at', '>=', $validated['from'].' 00:00:00');
        }
        if (isset($validated['to'])) {
            $query->where('scanned_at', '<=', $validated['to'].' 23:59:59');
        }

        return $this->paginated($query->paginate($validated['per_page'] ?? 20));
    }

    /* POST /attendance/unmatched-logs/{id}/map */
    public function mapUnmatchedLog(MapUnmatchedLogRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        $log = AttendanceLog::findOrFail($id);

        if ($log->employee_id !== null) {
            return $this->conflict('Log ini sudah memiliki karyawan; gunakan koreksi absensi untuk mengubahnya.');
        }

        $previous = $log->employee_id;

        DB::transaction(function () use ($log, $data, $request, $previous): void {
            // Hanya metadata pemrosesan yang diubah. Bukti asli (scanned_at,
            // fingerprint, payload) dikunci oleh guard append-only di model
            $log->update([
                'employee_id' => (int) $data['employee_id'],
                'processing_status' => AttendanceLog::STATUS_PENDING,
            ]);

            AttendanceLogMappingHistory::create([
                'attendance_log_id' => $log->id,
                'employee_id' => (int) $data['employee_id'],
                'actor_user_id' => $request->user()?->id,
                'previous_employee_id' => $previous !== null ? (string) $previous : null,
                'reason' => $data['reason'],
            ]);

            AuditService::record('ATTENDANCE', 'MAP_UNMATCHED_LOG', 'attendance_logs', $log->id, [
                'employee_id' => $previous,
                'processing_status' => AttendanceLog::STATUS_UNMATCHED,
            ], [
                'employee_id' => (int) $data['employee_id'],
                'processing_status' => AttendanceLog::STATUS_PENDING,
                'reason' => $data['reason'],
            ], $request);
        });

        AttendanceProcessingJob::dispatch([$log->id])
            ->onQueue((string) config('zkteco.processing_queue', 'attendance-processing'));

        return $this->success([
            'attendance_log_id' => $log->id,
            'employee_id' => (int) $data['employee_id'],
            'processing_status' => AttendanceLog::STATUS_PENDING,
            'reprocessing' => 'QUEUED',
        ], 'Log berhasil dipetakan dan dijadwalkan untuk diproses ulang');
    }
}
