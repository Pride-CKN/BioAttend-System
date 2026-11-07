<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'is_active',
        'sync_interval_minutes',
        'last_sync_at',
        'last_error_message',
        'last_error_code',
        'last_successful_connection_at',
        'consecutive_failures',
    ];

    /* Kredensial tidak boleh ikut ter-serialisasi (JSON, array, log) */
    protected $hidden = [
        'commkey_encrypted',
    ];

    // Tambah Is Active
    protected function casts(): array
    {
        return [
            'port'                  => 'integer',
            'commkey_encrypted'     => 'encrypted',
            'status'                => DeviceStatus::class,
            'is_active'             => 'boolean',
            'sync_interval_minutes' => 'integer',
            'consecutive_failures'  => 'integer',
            'last_sync_at'          => 'datetime',
            'last_successful_connection_at' => 'datetime',
        ];
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function checkpoint(): HasOne
    {
        return $this->hasOne(DeviceCheckpoint::class);
    }

    /* Pull log tiap 15 menit */
    public function isDueForPull(): bool
    {
    if ($this->last_sync_at === null) {
        return true;
    }

    $interval = $this->sync_interval_minutes
        ?? config('zkteco.pull_interval_minutes', 15);

    return $this->last_sync_at
        ->copy()
        ->addMinutes(max(1, (int) $interval))
        ->isPast();
    }

    public function markSynced(): void
    {
        $this->forceFill([
            'status'               => DeviceStatus::Online,
            'last_sync_at'         => now(),
            'last_successful_connection_at' => now(),
            'last_error_code'      => null,
            'last_error_message' => null,
            'consecutive_failures' => 0,
        ])->save();
    }

    public function markFailed(string $message, int $maxRetries = 3, ?string $errorCode = null): void
    {
        $failures = $this->consecutive_failures + 1;

        $this->forceFill([
            'consecutive_failures' => $failures,
            'last_error_code'      => $errorCode,
            'last_error_message'   => $message,
            'status'               => $failures >= $maxRetries
                ? DeviceStatus::Offline
                : $this->status,
        ])->save();
    }
}