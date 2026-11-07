<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/* Komponen PPh yang dikelola HRD (ekstensi, dapat dimatikan lewat setting PPH_ENABLED)
   sistem bukan payroll penuh */
class TaxComponent extends Model
{
    public const TYPE_DEDUCTION = 'DEDUCTION';
    public const TYPE_ALLOWANCE = 'ALLOWANCE';

    public const CALC_PERCENTAGE = 'PERCENTAGE';
    public const CALC_FIXED = 'FIXED';

    public const BASE_BASE = 'BASE';
    public const BASE_AFTER_ATTENDANCE = 'AFTER_ATTENDANCE';

    protected $fillable = [
        'code', 'name', 'type', 'calc_type', 'value', 'base_on',
        'is_active', 'is_system', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }
}
