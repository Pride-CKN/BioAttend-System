<?php

namespace App\Services\Device;

use App\Enums\DeviceStatus;
use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\DeviceCheckpoint;
use App\Models\DeviceSyncHistory;
use App\Models\IntegrationLog;
use App\Services\ZKTeco\Exceptions\ZktecoConnectionException;
use Illuminate\Support\Str;
use Throwable;

class DeviceSyncService
{
    public function __construct(private readonly DevicePullService $pull) {}

    /* Connection test (adapter) */
    public function testConnection(Device $device): ConnectionResult
    {
        $history = $this->startHistory($device, DeviceSyncHistory::TYPE_HEALTH, DeviceSyncHistory::TRIGGER_MANUAL);
        $startedAt = hrtime(true);
        $client = $this->pull->makeClient($device);

        try {
            $client->connect();
            $firmware = $client->firmwareVersion();
            $latency = $this->elapsedMs($startedAt);

            $this->finishHistory($history, DeviceSyncHistory::STATUS_SUCCESS, $latency);
            $device->forceFill(['status' => DeviceStatus::Online])->save();
            $this->integrationLog(
                $device, 'HEALTH', IntegrationLog::STATUS_SUCCESS, $latency,
                response: $firmware ? "firmware: {$firmware}" : null,
            );

            return ConnectionResult::connected($latency, $firmware);
        } catch (ZktecoConnectionException $e) {
            $latency = $this->elapsedMs($startedAt);
            $sdkMissing = $e->errorCode === ZktecoConnectionException::SDK_UNAVAILABLE;

            // Tidak SDK = Skipped
            $this->finishHistory(
                $history,
                $sdkMissing ? DeviceSyncHistory::STATUS_SKIPPED : DeviceSyncHistory::STATUS_FAILED,
                $latency,
                ['error_code' => $e->errorCode, 'error_summary' => Str::limit($e->getMessage(), 500)],
            );

            if (! $sdkMissing) {
                $this->integrationLog(
                    $device, 'HEALTH', IntegrationLog::STATUS_FAILED, $latency,
                    error: $e->getMessage(),
                );
            }

            return ConnectionResult::failed($e->errorCode, $e->getMessage(), $latency);
        } finally {
            $client->disconnect();
        }
    }

    /**
     * One sync cycle = one history row
     * queue job can apply retry / backoff.
     */
    public function sync(
        Device $device,
        string $trigger,
        ?string $correlationId = null,
        int $attempt = 1,
    ): DeviceSyncHistory {
        $history = $this->startHistory(
            $device,
            DeviceSyncHistory::TYPE_PULL_LOGS,
            $trigger,
            $correlationId,
            $attempt,
        );
        $startedAt = hrtime(true);

        try {
            $r = $this->pull->pull($device);

            $checkpoint = $this->advanceCheckpoint($device);

            $this->finishHistory(
                $history,
                $r['failed'] > 0
                    ? DeviceSyncHistory::STATUS_PARTIAL_SUCCESS
                    : DeviceSyncHistory::STATUS_SUCCESS,
                $r['duration_ms'],
                [
                    'total_fetched' => $r['fetched'],
                    'total_inserted' => $r['inserted'],
                    'total_duplicate' => $r['duplicate'],
                    'total_unmatched' => $r['unmatched'],
                    'total_failed' => $r['failed'],
                    'checkpoint_after' => $checkpoint->cursor,
                ],
            );

            // TODO: Send notifikasi jika offline sebelum
            $device->markSynced();

            $this->integrationLog(
                $device, 'PULL_LOGS', IntegrationLog::STATUS_SUCCESS, $r['duration_ms'],
                response: "fetched {$r['fetched']}, inserted {$r['inserted']}, duplicate {$r['duplicate']}, unmatched {$r['unmatched']}, failed {$r['failed']}",
            );

            return $history;
        } catch (ZktecoConnectionException $e) {
            $elapsed = $this->elapsedMs($startedAt);
            $sdkMissing = $e->errorCode === ZktecoConnectionException::SDK_UNAVAILABLE;
            $isTimeout = ! $sdkMissing && $this->looksLikeTimeout($e, $elapsed);

            $status = match (true) {
                $sdkMissing => DeviceSyncHistory::STATUS_SKIPPED,
                $isTimeout => DeviceSyncHistory::STATUS_TIMEOUT,
                default => DeviceSyncHistory::STATUS_FAILED,
            };

            $this->finishHistory($history, $status, $elapsed, [
                'error_code' => $isTimeout ? ZktecoConnectionException::TIMEOUT : $e->errorCode,
                'error_summary' => Str::limit($e->getMessage(), 500),
            ]);

            if (! $sdkMissing) {
                $device->markFailed((int) config('zkteco.retries'));

                $this->integrationLog(
                    $device, 'PULL_LOGS', IntegrationLog::STATUS_FAILED, $elapsed,
                    error: $e->getMessage(),
                );
            }

            throw $e;
        } catch (Throwable $e) {
            $this->finishHistory($history, DeviceSyncHistory::STATUS_FAILED, $this->elapsedMs($startedAt), [
                'error_code' => 'UNEXPECTED',
                'error_summary' => Str::limit($e->getMessage(), 500),
            ]);

            throw $e;
        }
    }

    private function startHistory(
        Device $device,
        string $syncType,
        string $trigger,
        ?string $correlationId = null,
        int $attempt = 1,
    ): DeviceSyncHistory {
        return DeviceSyncHistory::create([
            'device_id' => $device->id,
            'device_name' => $device->name,
            'sync_type' => $syncType,
            'trigger_source' => $trigger,
            'started_at' => now(),
            'attempt_count' => $attempt,
            'status' => DeviceSyncHistory::STATUS_RUNNING,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'checkpoint_before' => $syncType === DeviceSyncHistory::TYPE_PULL_LOGS
                ? $device->checkpoint?->cursor
                : null,
        ]);
    }

    private function finishHistory(DeviceSyncHistory $history, string $status, int $durationMs, array $extra = []): void
    {
        $history->update(array_merge([
            'status' => $status,
            'completed_at' => now(),
            'duration_ms' => $durationMs,
        ], $extra));
    }

    private function advanceCheckpoint(Device $device): DeviceCheckpoint
    {
        return DeviceCheckpoint::updateOrCreate(
            ['device_id' => $device->id],
            [
                'strategy' => DeviceCheckpoint::STRATEGY_FULL_SCAN,
                'last_scan_at' => now(),
                'last_inserted_log_id' => AttendanceLog::where('device_id', $device->id)->max('id'),
            ],
        );
    }

    private function looksLikeTimeout(ZktecoConnectionException $e, int $elapsedMs): bool
    {
        return $e->errorCode === ZktecoConnectionException::TIMEOUT
            || ($e->errorCode === ZktecoConnectionException::CONNECTION_FAILED
                && $elapsedMs >= (int) config('zkteco.timeout_seconds') * 1000);
    }

    private function integrationLog(
        Device $device,
        string $operation,
        string $status,
        int $durationMs,
        ?string $response = null,
        ?string $error = null,
    ): void {
        try {
            IntegrationLog::create([
                'service' => IntegrationLog::SERVICE_DEVICE,
                'operation' => $operation,
                'device_id' => $device->id,
                'status' => $status,
                'request_summary' => "{$device->ip_address}:{$device->port}", // never the commkey
                'response_summary' => $response,
                'error_message' => $error ? Str::limit($error, 1000) : null,
                'duration_ms' => $durationMs,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function elapsedMs(int $startedAtNs): int
    {
        return (int) ((hrtime(true) - $startedAtNs) / 1_000_000);
    }
}
