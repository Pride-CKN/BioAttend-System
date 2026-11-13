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

    public const STATUS_SKIPPED = 'SKIPPED';

    public const TYPE_PULL_LOGS = 'PULL_LOGS';

    public const TYPE_PUSH_EMPLOYEES = 'PUSH_EMPLOYEES';

    public const TYPE_HEALTH = 'HEALTH';

    public const TYPE_RECONCILE = 'RECONCILE';

    public const TRIGGER_SCHEDULED = 'scheduled';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_RETRY = 'retry';

    protected $fillable = [
        'device_id',
        'device_name',
        'sync_type',
        'trigger_source',
        'started_at',
        'completed_at',
        'duration_ms',
        'total_fetched',
        'total_inserted',
        'total_duplicate',
        'total_unmatched',
        'total_failed',
        'checkpoint_before',
        'checkpoint_after',
        'attempt_count',
        'status',
        'error_code',
        'error_summary',
        'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_ms' => 'integer',
            'total_fetched' => 'integer',
            'total_inserted' => 'integer',
            'total_duplicate' => 'integer',
            'total_unmatched' => 'integer',
            'total_failed' => 'integer',
            'attempt_count' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
