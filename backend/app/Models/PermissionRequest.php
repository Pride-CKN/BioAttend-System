<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* Pengajuan izin (penuh atau parsial)  */
class PermissionRequest extends Model
{
    public const TYPE_PERSONAL = 'PERSONAL';
    public const TYPE_SICK = 'SICK';
    public const TYPE_OTHER = 'OTHER';

    public const STATUS_PENDING_KADIV = 'PENDING_KADIV';
    public const STATUS_PENDING_HRD = 'PENDING_HRD';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'employee_id', 'type', 'date', 'start_time', 'end_time', 'duration_minutes',
        'reason', 'attachment_path', 'status', 'kadiv_approved_by', 'kadiv_approved_at',
        'hrd_approved_by', 'hrd_approved_at', 'rejection_note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'kadiv_approved_at' => 'datetime',
            'hrd_approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
