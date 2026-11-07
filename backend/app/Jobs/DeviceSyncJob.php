<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\DeviceSyncHistory;
use App\Services\Device\DeviceSyncService;
use App\Services\ZKTeco\Exceptions\ZktecoConnectionException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

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
    $this->correlationId ??= (string) Str::uuid();

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
            $service->sync(
                $device,
                $this->triggerSource,
                $this->correlationId,
                $this->attempts()
            );
        } catch (ZktecoConnectionException $e) {
            if ($e->errorCode === ZktecoConnectionException::SDK_UNAVAILABLE) {
                $this->fail($e);

                return;
            }

            throw $e;
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
            'status' => $e instanceof ZktecoConnectionException
                && $e->errorCode === ZktecoConnectionException::TIMEOUT
                    ? DeviceSyncHistory::STATUS_TIMEOUT
                    : DeviceSyncHistory::STATUS_FAILED,
            'completed_at' => now(),
            'error_code' => $e instanceof ZktecoConnectionException
                ? $e->errorCode
                : 'JOB_FAILED',
            'error_summary' => $e->getMessage(),
        ]);

        Log::warning('DeviceSyncJob failed', [
            'device_id' => $this->deviceId,
            'error' => $e->getMessage(),
        ]);
    }
}
