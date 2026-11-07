<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* Jejak audit — APPEND-ONLY
  Tidak memiliki updated_at; sekali ditulis tidak diubah */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'role', 'module', 'action', 'object_type', 'object_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'request_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
