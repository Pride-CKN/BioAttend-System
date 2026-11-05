<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'id', 
        'employee_id', 
        'work_date', 
        'shift_id', 
        'check_in_at',
        'check_out_at', 
        'work_duration_minutes', 
        'late_minutes',
        'status', 
        'is_incomplete', 
        'is_verified', 
        'verified_by', 
        'source'
    ];
}
