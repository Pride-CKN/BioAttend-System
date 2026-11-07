<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* Status push karyawan per perangkat (idempotent) */
class DeviceEmployeePush extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'device_id', 'employee_id', 'device_user_identifier',
        'operation', 'payload_hash', 'status', 'error_summary', 'pushed_at',
    ];

    protected function casts(): array
    {
        return ['pushed_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
