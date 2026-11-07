<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* Ledger warning EARLY_DEPARTURE — idempotency_key mencegah notifikasi ganda */
class AttendanceWarningEvent extends Model
{
    public const DELIVERY_PENDING = 'PENDING';
    public const DELIVERY_SENT = 'SENT';
    public const DELIVERY_PARTIAL = 'PARTIAL';
    public const DELIVERY_FAILED = 'FAILED';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_REVOKED = 'REVOKED';

    protected $fillable = [
        'attendance_record_id', 'employee_id', 'event_type', 'idempotency_key',
        'event_date', 'scheduled_end_at', 'actual_check_out_at', 'minutes_early',
        'payload', 'delivery_status', 'event_status',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'scheduled_end_at' => 'datetime',
            'actual_check_out_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }
}
