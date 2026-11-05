<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
  Hari libur aktif mengesampingkan kewajiban hadir
 */
class Holiday extends Model
{
    protected $fillable = [
        'date',
        'name',
        'type',
        'is_paid',
        'year',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_paid' => 'boolean',
            'year' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
