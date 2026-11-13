<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCheckpoint extends Model
{
    public const STRATEGY_NONE = 'NONE';

    public const STRATEGY_SDK_CURSOR = 'SDK_CURSOR';

    public const STRATEGY_TIMESTAMP_FALLBACK = 'TIMESTAMP_FALLBACK';

    public const STRATEGY_FULL_SCAN = 'FULL_SCAN';

    protected $fillable = [
        'device_id',
        'cursor',
        'last_scan_at',
        'strategy',
        'last_inserted_log_id',
    ];

    protected function casts(): array
    {
        return [
            'last_scan_at' => 'datetime',
            'last_inserted_log_id' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function isIncremental(): bool
    {
        return $this->strategy === self::STRATEGY_SDK_CURSOR;
    }
}
