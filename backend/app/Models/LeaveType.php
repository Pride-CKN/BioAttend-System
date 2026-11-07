<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = [
        'code', 'name', 'default_quota', 'max_days_per_request',
        'requires_attachment', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'requires_attachment' => 'boolean'];
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
