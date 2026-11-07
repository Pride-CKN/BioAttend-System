<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* Pengajuan lembur — durasi 1..12 jam, batas 7 hari */
class OvertimeRequest extends Model
{
    public const STATUS_PENDING_KADIV = 'PENDING_KADIV';
    public const STATUS_PENDING_HRD = 'PENDING_HRD';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'employee_id', 'date', 'start_time', 'end_time', 'claimed_minutes',
        'approved_minutes', 'multiplier', 'reason', 'status',
        'kadiv_approved_by', 'kadiv_approved_at', 'hrd_approved_by', 'hrd_approved_at',
        'rejection_note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'multiplier' => 'decimal:2',
            'kadiv_approved_at' => 'datetime',
            'hrd_approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
