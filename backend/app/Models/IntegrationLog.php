<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationLog extends Model
{
    protected $fillable = [
        'service', 'operation', 'device_id', 'status',
        'request_summary', 'response_summary', 'error_message', 'duration_ms',
    ];
}
