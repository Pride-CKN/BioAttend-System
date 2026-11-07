<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
    'code',
    'name',
    'start_time',
    'end_time',
    'late_tolerance_minutes',
    'min_work_hours',
    'is_night_shift',
    'is_active',
    ];

    public function schedule(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function attendanceRecords(): HasMany
    {
    return $this->hasMany(AttendanceRecord::class);
    }
}
