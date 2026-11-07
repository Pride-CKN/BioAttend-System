<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/* Konfigurasi jam kerja berjenjang
  Prioritas resolusi: EMPLOYEE › DIVISION › COMPANY */
class WorkScheduleConfig extends Model
{
    public const SCOPE_COMPANY = 'COMPANY';
    public const SCOPE_DIVISION = 'DIVISION';
    public const SCOPE_EMPLOYEE = 'EMPLOYEE';

    protected $fillable = [
        'scope_type', 'scope_id', 'effective_from', 'effective_to',
        'shift_id', 'work_days', 'late_tolerance_minutes', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'work_days' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ScheduleChangeHistory::class);
    }

    /* Apakah konfigurasi masih berlaku pada tanggal tertentu */
    public function isEffectiveOn(string $date): bool
    {
        if ($this->effective_from > $date) {
            return false;
        }

        return $this->effective_to === null || $this->effective_to >= $date;
    }
}
