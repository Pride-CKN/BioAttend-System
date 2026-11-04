<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat HASH password. Tidak pernah menyimpan password plaintext. */
class PasswordHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'password_hash', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
