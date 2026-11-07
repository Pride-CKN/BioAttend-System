<?php

namespace App\Support;

use InvalidArgumentException;

/* Kebijakan hashing kata sandi (Penyimpanan password — bcrypt cost >= 10)

  Aturan yang ditegakkan oleh kelas ini:
   1. Algoritma hashing wajib Bcrypt.
   2. Cost Bcrypt minimum 10. Nilai di bawah itu DITOLAK (dilempar pengecualian),
      tidak pernah diturunkan atau diterima secara diam-diam.
   3. Nilai awal yang direkomendasikan: 12 (PRD 20.3 menyaratkan >= 10)
   4. Sumber nilai: config('hashing.bcrypt.rounds') yang diisi dari env BCRYPT_ROUNDS
   5. Bila env belum diatur nilai kembali ke 12 */
final class PasswordHashPolicy
{
    /* Driver hashing yang diizinkan — tidak bisa diganti */
    public const DRIVER = 'bcrypt';

    /* Cost terendah yang masih dapat diterima. */
    public const MIN_ROUNDS = 10;

    /* Cost yang direkomendasikan sebagai konfigurasi awal */
    public const RECOMMENDED_ROUNDS = 12;

    /* Nilai bawaan bila BCRYPT_ROUNDS tidak diatur */
    public static function defaultRounds(): int
    {
        return self::RECOMMENDED_ROUNDS;
    }

    /* Normalisasi nilai mentah konfigurasi menjadi integer
      null / string kosong -> 12 (nilai bawaan) */
    public static function resolveRounds(mixed $value): int
    {
        if ($value === null || $value === '' || $value === false) {
            return self::RECOMMENDED_ROUNDS;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException(
                'Konfigurasi BCRYPT_ROUNDS tidak valid: "'.$value
                .'". Isikan bilangan bulat minimal '.self::MIN_ROUNDS.'.'
            );
        }

        return (int) $value;
    }

    /* Tolak cost yang berada di bawah ambang minimum */
    public static function validateRounds(int $rounds): void
    {
        if ($rounds < self::MIN_ROUNDS) {
            throw new InvalidArgumentException(
                'Konfigurasi BCRYPT_ROUNDS='.$rounds.' ditolak. Nilai minimum adalah '
                .self::MIN_ROUNDS.' (PRD 20.3); nilai yang disarankan '.self::RECOMMENDED_ROUNDS.'.'
            );
        }
    }

    /* Cost Bcrypt yang aktif pada aplikasi saat ini */
    public static function rounds(): int
    {
        $rounds = self::resolveRounds(config('hashing.bcrypt.rounds'));

        self::validateRounds($rounds);

        return $rounds;
    }

    /* eriksa keseluruhan konfigurasi hashing aktif.
      Dipanggil saat aplikasi boot; konfigurasi yang tidak memenuhi syarat
      langsung dihentikan agar tidak pernah dipakai untuk menyimpan password */
    public static function assertConfiguration(): void
    {
        $driver = strtolower((string) (config('hashing.driver') ?: self::DRIVER));

        if ($driver !== self::DRIVER) {
            throw new InvalidArgumentException(
                'Driver hashing "'.$driver.'" tidak diizinkan. Wajib "'.self::DRIVER.'" (PRD 20.3).'
            );
        }

        self::validateRounds(self::resolveRounds(config('hashing.bcrypt.rounds')));
    }

    /* Apakah nilai tersebut merupakan hash Bcryp */
    public static function isBcrypt(?string $hash): bool
    {
        if (! $hash) {
            return false;
        }

        return in_array(substr($hash, 0, 4), ['$2y$', '$2a$', '$2b$'], true);
    }

    /* Cost yang tersimpan di dalam hash; null bila bukan Bcrypt */
    public static function costOf(?string $hash): ?int
    {
        if (! self::isBcrypt($hash)) {
            return null;
        }

        $cost = password_get_info($hash)['options']['cost'] ?? null;

        return is_int($cost) ? $cost : null;
    }

    /* Apakah hash perlu diperkuat — yaitu bukan Bcrypt, atau cost-nya lebih
      rendah dari konfigurasi aktif. Hash yang LEBIH kuat tidak pernah diturunkan */
    public static function needsStrongerHash(?string $hash): bool
    {
        if (! self::isBcrypt($hash)) {
            return true;
        }

        $cost = self::costOf($hash);

        return $cost === null || $cost < self::rounds();
    }
}
