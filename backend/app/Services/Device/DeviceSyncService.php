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

    /**
     * Connection test (adapter)
     */
    public function testConnection(Device $device): ConnectionResult
    {
        $startedAt = hrtime(true);
        $client = $this->pull->makeClient($device);
 
        try {
            $client->connect();
            $firmware = $client->firmwareVersion();
            $latency = $this->elapsedMs($startedAt);
 
            $device->forceFill([
                'status'               => DeviceStatus::Online,
                'last_error'           => null,
                'last_error_code'      => null,
                'last_successful_connection_at' => now(),
                'consecutive_failures' => 0,
            ])->save();
 
            return ConnectionResult::connected($latency, $firmware);
        } catch (ZktecoConnectionException $e) {
            // A missing SDK says nothing about the device itself.
            if ($e->errorCode !== ZktecoConnectionException::SDK_UNAVAILABLE) {
                $device->forceFill([
                    'last_error'      => $e->getMessage(),
                    'last_error_code' => $e->errorCode,
                ])->save();
            }
 
            return ConnectionResult::failed($e->errorCode, $e->getMessage(), $this->elapsedMs($startedAt));
        } finally {
            $client->disconnect();
        }
    }

    /**
     * One sync cycle = one history row
     * queue job can apply retry / backoff.
     */
    public function sync(Device $device, string $trigger, ?string $correlationId = null): DeviceSyncHistory
    {
        $history = DeviceSyncHistory::create([
            'device_id'      => $device->id,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'trigger'        => $trigger,
            'status'         => DeviceSyncHistory::STATUS_RUNNING,
            'started_at'     => now(),
        ]);
 
        $startedAt = hrtime(true);
 
        try {
            $r = $this->pull->pull($device);
 
            $history->update([
                'status'           => $r['skipped'] > 0
                    ? DeviceSyncHistory::STATUS_PARTIAL_SUCCESS
                    : DeviceSyncHistory::STATUS_SUCCESS,
                'finished_at'      => now(),
                'duration_ms'      => $r['duration_ms'],
                'records_fetched'  => $r['fetched'],
                'records_inserted' => $r['inserted'],
                'records_unmapped' => $r['unmapped'],
                'records_skipped'  => $r['skipped'],
            ]);

            $device->markSynced();
 
            return $history;
        } catch (ZktecoConnectionException $e) {
            $elapsed = $this->elapsedMs($startedAt);
            $isTimeout = $e->errorCode === ZktecoConnectionException::TIMEOUT
                || ($e->errorCode === ZktecoConnectionException::CONNECTION_FAILED
                    && $elapsed >= (int) config('zkteco.timeout_seconds') * 1000);
 
            $history->update([
                'status'        => $isTimeout
                    ? DeviceSyncHistory::STATUS_TIMEOUT
                    : DeviceSyncHistory::STATUS_FAILED,
                'finished_at'   => now(),
                'duration_ms'   => $elapsed,
                'error_code'    => $isTimeout ? ZktecoConnectionException::TIMEOUT : $e->errorCode,
                'error_message' => Str::limit($e->getMessage(), 500),
            ]);
 
            if ($e->errorCode !== ZktecoConnectionException::SDK_UNAVAILABLE) {
                $device->markFailed($e->getMessage(), (int) config('zkteco.retries'), $e->errorCode);
                // TODO: add notification
            }
 
            throw $e;
        } catch (Throwable $e) {
            $history->update([
                'status'        => DeviceSyncHistory::STATUS_FAILED,
                'finished_at'   => now(),
                'duration_ms'   => $this->elapsedMs($startedAt),
                'error_code'    => 'UNEXPECTED',
                'error_message' => Str::limit($e->getMessage(), 500),
            ]);
 
            throw $e;
        }
    }

    private function elapsedMs(int $startedAtNs): int
    {
        return (int) ((hrtime(true) - $startedAtNs) / 1_000_000);
    }
}