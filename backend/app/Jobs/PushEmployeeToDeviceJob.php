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

    public int $tries;

    public array $backoff;

    public int $timeout = 60;

    public int $uniqueFor = 900;

    public function __construct(public int $pushId)
    {
        $this->tries = max(1, (int) config('zkteco.retries', 3));
        $this->backoff = (array) config('zkteco.backoff_seconds', [10, 30, 60]);
    }

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
                DeviceEmployeePush::OPERATION_UPSERT => $adapter->pushEmployee(
                    $device,
                    $push->employee,
                    (string) $push->device_user_identifier
                ),
                DeviceEmployeePush::OPERATION_DISABLE => $adapter->disableEmployee(
                    $device,
                    $push->employee,
                    (string) $push->device_user_identifier
                ),
                default => throw new \InvalidArgumentException('Operasi push karyawan tidak dikenal.'),
            };

            if (! $ok) {
                throw new \RuntimeException('Perangkat menolak data karyawan.');
            }

            $push->update([
                'status' => DeviceEmployeePush::STATUS_SUCCESS,
                'pushed_at' => now(),
                'error_summary' => null,
            ]);

            $this->recordIntegrationLog($push, IntegrationLog::STATUS_SUCCESS, $startedAt);
        } catch (ZktecoConnectionException $e) {
            $push->update(['status' => DeviceEmployeePush::STATUS_FAILED, 'error_summary' => $e->getMessage()]);
            $this->recordIntegrationLog($push, IntegrationLog::STATUS_FAILED, $startedAt, $e->getMessage());
            throw $e;
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
