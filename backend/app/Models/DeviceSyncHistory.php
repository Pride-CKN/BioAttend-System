<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSyncHistory extends Model
{
    public const STATUS_RUNNING = 'RUNNING';
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_PARTIAL_SUCCESS = 'PARTIAL_SUCCESS';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_TIMEOUT = 'TIMEOUT';

    protected $fillable = [
        'device_id',
        'correlation_id',
        'trigger',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'records_fetched',
        'records_inserted',
        'records_unmapped',
        'records_skipped',
        'error_code',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at'       => 'datetime',
            'finished_at'      => 'datetime',
            'duration_ms'      => 'integer',
            'records_fetched'  => 'integer',
            'records_inserted' => 'integer',
            'records_unmapped' => 'integer',
            'records_skipped'  => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}