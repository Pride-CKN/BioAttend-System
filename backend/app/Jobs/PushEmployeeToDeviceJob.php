<?php

namespace App\Jobs;

use App\Models\DeviceEmployeePush;
use App\Services\ZKTeco\Contracts\DeviceAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Kirim SATU karyawan ke SATU perangkat. Idempoten berdasar payload_hash. */
class PushEmployeeToDeviceJob implements ShouldQueue, ShouldBeUnique
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

        $connected = false;
        try {
            $adapter->connect($device);
            $connected = true;
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
        } catch (\App\Services\ZKTeco\Exceptions\SdkUnavailableException $e) {
            $push->update(['status' => DeviceEmployeePush::STATUS_FAILED, 'error_summary' => $e->getMessage()]);
            $this->fail($e);
        } catch (Throwable $e) {
            $push->update(['status' => DeviceEmployeePush::STATUS_FAILED, 'error_summary' => $e->getMessage()]);
            throw $e;
        } finally {
            // Selalu lepaskan koneksi, termasuk ketika operasi push melempar.
            try {
                $adapter->disconnect($device);
            } catch (Throwable $disconnectError) {
                report($disconnectError);
            }
        }
    }
}
