<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\DeviceSyncHistory;
use App\Services\Device\DeviceSyncService;
use App\Services\ZKTeco\Exceptions\DeviceConnectionException;
use App\Services\ZKTeco\Exceptions\SdkUnavailableException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Satu pekerjaan sinkronisasi untuk SATU perangkat.
 *
 * - ShouldBeUnique (kunci = device id) mencegah dua job aktif pada perangkat
 *   yang sama, ditambah distributed lock di DeviceSyncService.
 * - Retry + exponential backoff HANYA untuk kegagalan sementara.
 * - Error permanen (commkey salah, IP salah, SDK tidak ada) langsung gagal.
 */
class DeviceSyncJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public int $timeout = 180;

    public int $uniqueFor = 900;

    public function __construct(
        public int $deviceId,
        public string $triggerSource = 'scheduled',
        public ?string $correlationId = null,
    ) {
        $this->tries = max(1, (int) config('zkteco.sync.max_attempts', 3));
        $this->timeout = max(30, (int) config('zkteco.sync.execution_timeout', 120));
        $this->backoff = (array) config('zkteco.sync.backoff', [30, 120, 300]);
    }

    public function uniqueId(): string
    {
        return 'device-sync:'.$this->deviceId;
    }

    public function handle(DeviceSyncService $service): void
    {
        $device = Device::find($this->deviceId);

        if (! $device || ! $device->is_active) {
            return;
        }

        try {
            $service->sync($device, [
                'trigger_source' => $this->triggerSource,
                'attempt_count' => $this->attempts(),
                'correlation_id' => $this->correlationId ?? (string) Str::uuid(),
            ]);
        } catch (SdkUnavailableException $e) {
            // Dependency eksternal belum tersedia — tidak ada gunanya retry.
            $this->fail($e);
        } catch (DeviceConnectionException $e) {
            if (! $e->isTemporary()) {
                $this->fail($e);
            }

            throw $e; // temporary -> exponential backoff
        }
    }

    public function failed(Throwable $e): void
    {
        $history = DeviceSyncHistory::query()
            ->where('device_id', $this->deviceId)
            ->where('status', DeviceSyncHistory::STATUS_RUNNING)
            ->latest('started_at')
            ->first();

        $history?->update([
            'status' => $e instanceof DeviceConnectionException && $e->errorCode === 'TIMEOUT'
                ? DeviceSyncHistory::STATUS_TIMEOUT
                : DeviceSyncHistory::STATUS_FAILED,
            'completed_at' => now(),
            'error_code' => $e instanceof SdkUnavailableException ? 'SDK_UNAVAILABLE' : 'JOB_FAILED',
            'error_summary' => $e->getMessage(),
        ]);

        Log::warning('DeviceSyncJob failed', [
            'device_id' => $this->deviceId,
            'error' => $e->getMessage(),
        ]);
    }
}
