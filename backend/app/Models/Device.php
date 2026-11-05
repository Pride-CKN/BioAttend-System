<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'id', 
        'name', 
        'model', 
        'ip_address', 
        'port', 
        'commkey_encrypted',
        'location', 
        'status', 
        'last_sync_at'
    ];

    protected function casts(): array
    {
        return ['last_sync_at' => 'date'];
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
