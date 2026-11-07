<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* Riwayat perubahan konfigurasi jam kerja (append-only) */
class ScheduleChangeHistory extends Model
{
    protected $fillable = [
        'work_schedule_config_id', 'scope_type', 'scope_id',
        'old_values', 'new_values', 'changed_by', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(WorkScheduleConfig::class, 'work_schedule_config_id');
    }
}
