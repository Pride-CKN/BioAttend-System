<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    protected $fillable = [
        'name',
        'model',
        'serial_number',
        'ip_address',
        'port',
        'commkey_encrypted',
        'location',
        'status',
        'last_sync_at',
        'sync_offset_seconds',
        'sync_interval_minutes',
        'is_active',
    ];

    /* Kredensial tidak boleh ikut ter-serialisasi (JSON, array, log) */
    protected $hidden = [
        'commkey_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'commkey_encrypted' => 'encrypted',
            'status' => DeviceStatus::class,
            'last_sync_at' => 'datetime',
            'sync_offset_seconds' => 'integer',
            'sync_interval_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function syncHistories(): HasMany
    {
        return $this->hasMany(DeviceSyncHistory::class);
    }

    public function checkpoint(): HasOne
    {
        return $this->hasOne(DeviceCheckpoint::class);
    }

    protected function consecutiveFailures(): Attribute
    {
        return Attribute::get(function (): int {
            if (! $this->exists) {
                return 0;
            }

            $types = $this->healthSyncTypes();

            $lastOk = $this->syncHistories()
                ->whereIn('sync_type', $types)
                ->whereIn('status', [
                    DeviceSyncHistory::STATUS_SUCCESS,
                    DeviceSyncHistory::STATUS_PARTIAL_SUCCESS,
                ])
                ->max('id');

            return $this->syncHistories()
                ->whereIn('sync_type', $types)
                ->whereIn('status', [
                    DeviceSyncHistory::STATUS_FAILED,
                    DeviceSyncHistory::STATUS_TIMEOUT,
                ])
                ->when($lastOk, fn ($q) => $q->where('id', '>', $lastOk))
                ->count();
        });
    }

    protected function lastErrorCode(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->exists) {
                return null;
            }

            $last = $this->syncHistories()
                ->whereIn('status', [
                    DeviceSyncHistory::STATUS_SUCCESS,
                    DeviceSyncHistory::STATUS_PARTIAL_SUCCESS,
                    DeviceSyncHistory::STATUS_FAILED,
                    DeviceSyncHistory::STATUS_TIMEOUT,
                ])
                ->latest('id')
                ->first();

            $failed = in_array($last?->status, [
                DeviceSyncHistory::STATUS_FAILED,
                DeviceSyncHistory::STATUS_TIMEOUT,
            ], true);

            return $failed ? $last->error_code : null;
        });
    }

    protected function lastSuccessfulConnectionAt(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->exists) {
                return null;
            }

            return $this->syncHistories()
                ->whereIn('status', [
                    DeviceSyncHistory::STATUS_SUCCESS,
                    DeviceSyncHistory::STATUS_PARTIAL_SUCCESS,
                ])
                ->latest('id')
                ->first()
                ?->completed_at;
        });
    }

    private function healthSyncTypes(): array
    {
        return [DeviceSyncHistory::TYPE_PULL_LOGS, DeviceSyncHistory::TYPE_HEALTH];
    }

    public function isDueForPull(): bool
    {
        if ($this->last_sync_at === null) {
            return true;
        }

        return $this->last_sync_at
            ->copy()
            ->addMinutes(max(1, (int) $this->sync_interval_minutes))
            ->isPast();
    }

    public function markSynced(): void
    {
        $this->forceFill([
            'status' => DeviceStatus::Online,
            'last_sync_at' => now(),
        ])->save();
    }

    public function markFailed(int $maxRetries = 3): bool
    {
        if ($this->status === DeviceStatus::Offline || $this->consecutive_failures < $maxRetries) {
            return false;
        }

        $this->forceFill(['status' => DeviceStatus::Offline])->save();

        return true;
    }
}
