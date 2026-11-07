<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* Jejak mapping manual log unmatched */
class AttendanceLogMappingHistory extends Model
{
    protected $fillable = [
        'attendance_log_id', 'employee_id', 'actor_user_id',
        'previous_employee_id', 'reason',
    ];

    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
