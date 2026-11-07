<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_DONE = 'DONE';
    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'name', 'type', 'parameters', 'period_start', 'period_end',
        'format', 'status', 'file_path', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }
}
