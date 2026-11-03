<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun baru (dibuat HRD) wajib mengganti kata sandi sementara sebelum boleh
 * mengakses modul lain / master prompt Bagian aktivasi akun
 *
 * Endpoint yang tetap diizinkan hanya yang memang dibutuhkan untuk menyelesaikan
 * proses ganti kata sandi. Respons 428 (Precondition Required) memudahkan SPA
 * mengarahkan pengguna ke halaman penggantian kata sandi.
 */
class EnsurePasswordChanged
{
    private const ALLOWED = [
        'api/v1/auth/logout',
        'api/v1/auth/logout-all',
        'api/v1/auth/change-password',
        'api/v1/auth/profile',
        'api/v1/auth/activate',
        'api/v1/auth/sessions',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        foreach (self::ALLOWED as $path) {
            if ($request->is($path) || $request->is($path.'/*')) {
                return $next($request);
            }
        }

        $message = 'Anda wajib mengganti kata sandi sementara sebelum mengakses fitur lain.';

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => 'PASSWORD_CHANGE_REQUIRED',
                'message' => $message,
                'details' => null,
            ],
            'request_id' => app()->bound('request_id') ? app('request_id') : null,
        ], 428);
    }
}
