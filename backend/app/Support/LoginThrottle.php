<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/* Kunci throttle login yang sama dipakai middleware `throttle:login`,
  controller (CAPTCHA), dan pembersihan saat login berhasil
 
  Kombinasi email + IP mencegah satu akun dibrute dari banyak IP sekaligus
  tanpa membuat seluruh jaringan kantor saling mengunci
 
  Penghitung kegagalan sengaja memakai cache terpisah, bukan internal
  RateLimiter, agar ambang CAPTCHA deterministik dan dapat diuji tanpa
  bergantung pada urutan hit middleware */
class LoginThrottle
{
    private const FAILURE_TTL_MINUTES = 15;

    public static function key(Request $request): string
    {
        $email = Str::lower(trim((string) $request->input('email', '')));

        return 'login|'.hash('sha256', $email.'|'.$request->ip());
    }

    private static function failureKey(Request $request): string
    {
        return 'login-failures|'.self::key($request);
    }

    public static function attempts(Request $request): int
    {
        return RateLimiter::attempts(self::key($request));
    }

    public static function failures(Request $request): int
    {
        return (int) Cache::get(self::failureKey($request), 0);
    }

    public static function recordFailure(Request $request): int
    {
        $count = self::failures($request) + 1;
        Cache::put(self::failureKey($request), $count, now()->addMinutes(self::FAILURE_TTL_MINUTES));

        return $count;
    }

    /* Reset penghitung setelah autentikasi berhasil */
    public static function clear(Request $request): void
    {
        RateLimiter::clear(self::key($request));
        Cache::forget(self::failureKey($request));
    }
}
