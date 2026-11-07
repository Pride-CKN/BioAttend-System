<?php

namespace App\Services\Auth\Captcha;

use App\Services\Auth\Captcha\Contracts\CaptchaVerifier;
use Illuminate\Http\Request;

/* Implementasi default saat belum ada provider CAPTCHA
 
  SENGAJA selalu menolak dan melaporkan NOT_CONFIGURED. Tidak ada jalur
  "anggap valid" karena itu akan membuat brute force lolos dan merupakan
  klaim keamanan palsu. Status integrasi: BLOCKED  */
class NullCaptchaVerifier implements CaptchaVerifier
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function verify(string $token, Request $request): bool
    {
        return false;
    }

    public function status(): string
    {
        return 'NOT_CONFIGURED';
    }
}
