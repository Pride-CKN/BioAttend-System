<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* Pengajuan koreksi absensi — persetujuan berjenjang KADIV -> HRD  */
class AttendanceCorrection extends Model
{
    public const STATUS_PENDING_KADIV = 'PENDING_KADIV';
    public const STATUS_PENDING_HRD = 'PENDING_HRD';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'attendance_record_id', 'employee_id', 'field', 'old_value', 'new_value',
        'reason', 'attachment_path', 'status', 'approved_by_kadiv', 'kadiv_approved_at',
        'approved_by_hrd', 'hrd_approved_at', 'rejection_note',
    ];

    protected function casts(): array
    {
        return [
            'kadiv_approved_at' => 'datetime',
            'hrd_approved_at' => 'datetime',
        ];
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
