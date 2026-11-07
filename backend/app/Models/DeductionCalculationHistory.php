<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* Riwayat setiap eksekusi perhitungan deduction */
class DeductionCalculationHistory extends Model
{
    public const ACTION_CALCULATE = 'CALCULATE';
    public const ACTION_RECALCULATE = 'RECALCULATE';
    public const ACTION_INPUT_NOMINAL = 'INPUT_NOMINAL';
    public const ACTION_CONFIRM = 'CONFIRM';
    public const ACTION_FINALIZE = 'FINALIZE';
    public const ACTION_UPDATE_PPH = 'UPDATE_PPH';

    protected $fillable = [
        'deduction_record_id', 'period', 'employee_id', 'total_events', 'tolerated_events',
        'charged_events', 'alpha_events', 'early_departure_events', 'alpha_rate',
        'rate_per_event', 'total_percentage', 'base_amount', 'deduction_amount',
        'raw_final_amount', 'rounding_adjustment', 'pph_amount', 'pph_details', 'final_amount',
        'status', 'action', 'actor_user_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'total_percentage' => 'decimal:2',
            'rate_per_event' => 'decimal:2',
            'alpha_rate' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'pph_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'pph_details' => 'array',
        ];
    }

    public function deductionRecord(): BelongsTo
    {
        return $this->belongsTo(DeductionRecord::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
