<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class Employee extends Model
{
    use HasFactory;
    
    /* karyawan tidak dihapus fisik, cukup dinonaktifkan */
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_RESIGNED = 'RESIGNED';

    protected $fillable = [
        'nip',
        'device_user_identifier',
        'employee_code',
        'office_id',
        'full_name',
        'email',
        'phone',
        'gender',
        'join_date',
        'employment_status',
        'division_id',
        'unit_id',
        'position_id',
        'manager_id',
        'photo_path',
    ];

    protected function casts(): array
    {
    return [
        'join_date' => 'date',
        'office_id' => 'integer',
        'division_id' => 'integer',
        'unit_id' => 'integer',
    ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function division(): BelongsTo
    {
    return $this->belongsTo(Division::class, 'division_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function employeeCodeHistories(): HasMany
{
    return $this->hasMany(EmployeeCodeHistory::class);
}

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function deductionRecords(): HasMany
    {
        return $this->hasMany(DeductionRecord::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function permissionRequests(): HasMany
    {
        return $this->hasMany(PermissionRequest::class);
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function isActive(): bool
    {
        return $this->employment_status === self::STATUS_ACTIVE;
    }
}