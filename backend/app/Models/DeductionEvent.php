<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* Rincian event pelanggaran pada satu perhitungan deduction */
class DeductionEvent extends Model
{
    protected $fillable = [
        'deduction_record_id', 'attendance_record_id', 'event_date',
        'event_type', 'sequence', 'is_tolerated', 'percentage',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'is_tolerated' => 'boolean',
            'percentage' => 'decimal:2',
        ];
    }

    public function deductionRecord(): BelongsTo
    {
        return $this->belongsTo(DeductionRecord::class);
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function isAlpha(): bool
    {
        return $this->event_type === AttendanceRecord::EVENT_ABSENT;
    }
}
