<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'name',
        'model',
        'ip_address',
        'port',
        'commkey_encrypted',
        'location',
        'status',
        'pull_interval_minutes',
        'last_sync_at',
        'last_error',
        'consecutive_failures',
    ];

    /**
     * PRD-14.2 Kredensial tidak boleh ikut ter-serialisasi (JSON, array, log).
     */
    protected $hidden = [
        'commkey_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'port'                  => 'integer',
            'commkey_encrypted'     => 'encrypted',
            'status'                => DeviceStatus::class,
            'pull_interval_minutes' => 'integer',
            'consecutive_failures'  => 'integer',
            'last_sync_at'          => 'datetime',
        ];
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    /**
     * PRD-14.2 Pull log tiap 15 menit.
     */
    public function isDueForPull(): bool
    {
        if ($this->last_sync_at === null) {
            return true;
        }

        return $this->last_sync_at
            ->addMinutes($this->pull_interval_minutes ?? 15)
            ->isPast();
    }

    public function markSynced(): void
    {
        $this->forceFill([
            'status'               => DeviceStatus::Online,
            'last_sync_at'         => now(),
            'last_error'           => null,
            'consecutive_failures' => 0,
        ])->save();
    }

    public function markFailed(string $message, int $maxRetries = 3): void
    {
        $failures = $this->consecutive_failures + 1;

        $this->forceFill([
            'consecutive_failures' => $failures,
            'last_error'           => $message,
            'status'               => $failures >= $maxRetries
                ? DeviceStatus::Offline
                : $this->status,
        ])->save();
    }
}