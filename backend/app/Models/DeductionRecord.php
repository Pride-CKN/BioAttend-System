<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
  Hasil perhitungan potongan per karyawan per periode
 
  ATURAN ALPHA (permintaan eksplisit):
    alpha / tanpa keterangan dikenakan potongan 4% LANGSUNG sejak kejadian
    pertama dan TIDAK mendapat jatah toleransi.
    P = (Ec x 2%) + (jumlah_alpha x 4%)
 */
class DeductionRecord extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_CALCULATED = 'CALCULATED';
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_FINAL = 'FINAL';

    protected $fillable = [
        'employee_id', 'period', 'total_events', 'tolerated_events', 'charged_events',
        'rate_per_event', 'total_percentage', 'alpha_events', 'early_departure_events',
        'alpha_rate', 'alpha_percentage', 'base_amount', 'deduction_amount',
        'raw_final_amount', 'rounding_adjustment', 'pph_amount', 'pph_details', 'final_amount',
        'status', 'calculated_at', 'confirmed_by', 'confirmed_at', 'confirmation_note',
    ];

    protected function casts(): array
    {
        return [
            'total_percentage' => 'decimal:2',
            'rate_per_event' => 'decimal:2',
            'alpha_rate' => 'decimal:2',
            'alpha_percentage' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'raw_final_amount' => 'decimal:2',
            'rounding_adjustment' => 'decimal:2',
            'pph_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'pph_details' => 'array',
            'calculated_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeductionEvent::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DeductionCalculationHistory::class);
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    public function canBeModified(): bool
    {
        return ! $this->isFinal();
    }
}
