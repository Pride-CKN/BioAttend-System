<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    protected $fillable = [
        'id',
        'device_id', 
        'employee_id', 
        'scanned_at', 
        'scan_type',
        'raw_payload', 
        'processing_status', 
        'is_duplicate',
        'primary_log_id'
    ];

    protected function casts(): array
    {
        return ['scanned_at' => 'date'];
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function primaryLog()
    {
        return $this->belongsTo(AttendanceLog::class, 'primary_log_id');
    }
}
