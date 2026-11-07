<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* Pengajuan cuti — persetujuan berjenjang KADIV -> HRD  */
class LeaveRequest extends Model
{
    public const STATUS_PENDING_KADIV = 'PENDING_KADIV';
    public const STATUS_PENDING_HRD = 'PENDING_HRD';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'employee_id', 'leave_type_id', 'start_date', 'end_date', 'total_days',
        'reason', 'attachment_path', 'status', 'kadiv_approved_by', 'kadiv_approved_at',
        'hrd_approved_by', 'hrd_approved_at', 'rejection_note',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_days' => 'decimal:1',
            'kadiv_approved_at' => 'datetime',
            'hrd_approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
