<?php

namespace App\Services\Device;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\DeviceSyncHistory;
use App\Services\ZKTeco\Exceptions\ZktecoConnectionException;
use Illuminate\Support\Str;
use Throwable;

class DeviceSyncService
{
    public function __construct(private readonly DevicePullService $pull) {}

    /* Connection test (adapter) */
    public function testConnection(Device $device): ConnectionResult
    {
        $startedAt = hrtime(true);
        $client = $this->pull->makeClient($device);

        try {
            $client->connect();
            $firmware = $client->firmwareVersion();
            $latency = $this->elapsedMs($startedAt);

            $device->forceFill([
                'status' => DeviceStatus::Online,
                'last_error_message' => null,
                'last_error_code' => null,
                'last_successful_connection_at' => now(),
                'consecutive_failures' => 0,
            ])->save();

            return ConnectionResult::connected($latency, $firmware);
        } catch (ZktecoConnectionException $e) {
            // A missing SDK says nothing about the device itself.
            if ($e->errorCode !== ZktecoConnectionException::SDK_UNAVAILABLE) {
                $device->forceFill([
                    'last_error_message' => $e->getMessage(),
                    'last_error_code' => $e->errorCode,
                ])->save();
            }

            return ConnectionResult::failed(
                $e->errorCode,
                $e->getMessage(),
                $this->elapsedMs($startedAt)
            );
        } finally {
            $client->disconnect();
        }
    }

    /* One sync cycle = one history row
       queue job can apply retry / backoff */
    public function sync(
        Device $device,
        string $trigger,
        ?string $correlationId = null,
        int $attemptCount = 1
    ): DeviceSyncHistory {
        $history = DeviceSyncHistory::create([
            'device_id' => $device->id,
            'device_name' => $device->name,
            'sync_type' => 'PULL_LOGS',
            'trigger_source' => $trigger,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'attempt_count' => max(1, $attemptCount),
            'status' => DeviceSyncHistory::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $startedAt = hrtime(true);

        try {
            $r = $this->pull->pull($device);

            $history->update([
                'status' => $r['skipped'] > 0
                    ? DeviceSyncHistory::STATUS_PARTIAL_SUCCESS
                    : DeviceSyncHistory::STATUS_SUCCESS,
                'completed_at' => now(),
                'duration_ms' => $r['duration_ms'],
                'total_fetched' => $r['fetched'],
                'total_inserted' => $r['inserted'],
                'total_duplicate' => $r['duplicate'],
                'total_unmatched' => $r['unmapped'],
                'total_failed' => $r['skipped'],
            ]);

            $device->markSynced();

            return $history;
        } catch (ZktecoConnectionException $e) {
            $elapsed = $this->elapsedMs($startedAt);

            $isTimeout = $e->errorCode === ZktecoConnectionException::TIMEOUT
                || (
                    $e->errorCode === ZktecoConnectionException::CONNECTION_FAILED
                    && $elapsed >= (int) config('zkteco.timeout_seconds') * 1000
                );

            $history->update([
                'status' => $isTimeout
                    ? DeviceSyncHistory::STATUS_TIMEOUT
                    : DeviceSyncHistory::STATUS_FAILED,
                'completed_at' => now(),
                'duration_ms' => $elapsed,
                'error_code' => $isTimeout
                    ? ZktecoConnectionException::TIMEOUT
                    : $e->errorCode,
                'error_summary' => Str::limit($e->getMessage(), 500),
            ]);

            if ($e->errorCode !== ZktecoConnectionException::SDK_UNAVAILABLE) {
                $device->markFailed(
                    $e->getMessage(),
                    (int) config('zkteco.retries'),
                    $e->errorCode
                );
            }

            throw $e;
        } catch (Throwable $e) {
            $history->update([
                'status' => DeviceSyncHistory::STATUS_FAILED,
                'completed_at' => now(),
                'duration_ms' => $this->elapsedMs($startedAt),
                'error_code' => 'UNEXPECTED',
                'error_summary' => Str::limit($e->getMessage(), 500),
            ]);

            throw $e;
        }
    }

    private function elapsedMs(int $startedAtNs): int
    {
        return (int) ((hrtime(true) - $startedAtNs) / 1_000_000);
    }
}