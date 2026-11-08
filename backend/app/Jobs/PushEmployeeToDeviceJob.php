<?php

namespace App\Jobs;

use App\Models\DeviceEmployeePush;
use App\Models\IntegrationLog;
use App\Services\ZKTeco\Contracts\DeviceAdapter;
use App\Services\ZKTeco\Exceptions\ZktecoConnectionException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Kirim SATU karyawan ke SATU perangkat. Idempoten berdasar payload_hash. */
class PushEmployeeToDeviceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public int $timeout = 60;

    public int $uniqueFor = 900;

    public function __construct(public int $pushId) {}

    public function uniqueId(): string
    {
        return 'device-employee-push:'.$this->pushId;
    }

    public function handle(DeviceAdapter $adapter): void
    {
        $push = DeviceEmployeePush::with(['device', 'employee'])->find($this->pushId);

        if (! $push || ! $push->device || ! $push->employee || ! $push->device->is_active) {
            return;
        }

        if ($push->status === DeviceEmployeePush::STATUS_SUCCESS) {
            return;
        }

        $device = $push->device;

        $startedAt = hrtime(true);
        try {
            $adapter->connect($device);
            $ok = match (strtoupper((string) $push->operation)) {
                'UPSERT' => $adapter->pushEmployee(
                    $device,
                    $push->employee,
                    (string) $push->device_user_identifier
                ),
                'DISABLE' => $adapter->disableEmployee(
                    $device,
                    $push->employee,
                    (string) $push->device_user_identifier
                ),
                default => throw new \InvalidArgumentException('Operasi push karyawan tidak dikenal.'),
            };

            $push->update([
                'status' => $ok ? DeviceEmployeePush::STATUS_SUCCESS : DeviceEmployeePush::STATUS_FAILED,
                'pushed_at' => $ok ? now() : null,
                'error_summary' => $ok ? null : 'Perangkat menolak data karyawan.',
            ]);

            if (! $ok) {
                throw new \RuntimeException('Perangkat menolak data karyawan.');
            }
            $this->recordIntegrationLog($push, IntegrationLog::STATUS_SUCCESS, $startedAt);
        } catch (ZktecoConnectionException $e) {
            $push->update(['status' => DeviceEmployeePush::STATUS_FAILED, 'error_summary' => $e->getMessage()]);
            $this->recordIntegrationLog($push, IntegrationLog::STATUS_FAILED, $startedAt, $e->getMessage());

            if ($e->errorCode !== ZktecoConnectionException::SDK_UNAVAILABLE) {
                throw $e;
            }

            $this->fail($e);
        } catch (Throwable $e) {
            $push->update(['status' => DeviceEmployeePush::STATUS_FAILED, 'error_summary' => $e->getMessage()]);
            $this->recordIntegrationLog($push, IntegrationLog::STATUS_FAILED, $startedAt, $e->getMessage());
            throw $e;
        } finally {
            // Selalu lepaskan koneksi, termasuk ketika operasi push melempar
            try {
                $adapter->disconnect($device);
            } catch (Throwable $disconnectError) {
                report($disconnectError);
            }
        }
    }

    private function recordIntegrationLog(
        DeviceEmployeePush $push,
        string $status,
        int $startedAt,
        ?string $error = null,
    ): void {
        IntegrationLog::create([
            'service' => IntegrationLog::SERVICE_DEVICE,
            'operation' => 'PUSH_EMPLOYEE',
            'device_id' => $push->device_id,
            'status' => $status,
            'request_summary' => "employee_id: {$push->employee_id}; operation: {$push->operation}",
            'response_summary' => $status === IntegrationLog::STATUS_SUCCESS ? 'Employee device record updated.' : null,
            'error_message' => $error !== null ? str($error)->limit(1000) : null,
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
        ]);
    }
}
