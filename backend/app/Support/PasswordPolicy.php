<?php

namespace App\Support;

use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/* Kebijakan kata sandi

  - Minimal 8 karakter, mengandung huruf DAN angka
  - Tidak boleh memakai ulang 5 kata sandi terakhir
  - Riwayat menyimpan HASH bcrypt, bukan plaintext. Tabel ini berbeda dari
    audit_logs: audit log justru menyamarkan segala hal bernuansa password */
class PasswordPolicy
{
    public const HISTORY_LIMIT = 5;

    /** @return array<int, mixed> */
    public static function rules(bool $confirmed = true): array
    {
        $rules = [
            'required',
            'string',
            Password::min(8)->letters()->numbers(),
        ];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }

    /* Benarkah kata sandi ini pernah dipakai (hash saat ini + riwayat)? */
    public static function isReused(User $user, string $plain): bool
    {
        if ($user->password && Hash::check($plain, $user->password)) {
            return true;
        }

        $history = PasswordHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->pluck('password_hash');

        foreach ($history as $hash) {
            if (Hash::check($plain, $hash)) {
                return true;
            }
        }

        return false;
    }

    /* Simpan hash lama SEBELUM kata sandi diganti, lalu pangkas riwayat agar
      hanya HISTORY_LIMIT hash terakhir yang tersimpan (data minimisasi) */
    public static function rememberCurrent(User $user): void
    {
        if (! $user->password) {
            return;
        }

        PasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => $user->password,
            'created_at' => now(),
        ]);

        $keepIds = PasswordHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->pluck('id')
            ->all();

        if ($keepIds !== []) {
            PasswordHistory::query()
                ->where('user_id', $user->id)
                ->whereNotIn('id', $keepIds)
                ->delete();
        }
    }
}
