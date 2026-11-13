<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationLog extends Model
{
    public const SERVICE_DEVICE = 'DEVICE';

    public const STATUS_SUCCESS = 'SUCCESS';

    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'service', 'operation', 'device_id', 'status',
        'request_summary', 'response_summary', 'error_message', 'duration_ms',
    ];
}
