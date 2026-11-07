<?php

namespace App\Services\Auth\Captcha\Contracts;

use Illuminate\Http\Request;

/* Kontrak verifikasi CAPTCHA. Implementasi produksi WAJIB melakukan
  verifikasi ke provider nyata (server-to-server) dan tidak boleh
  mengembalikan true hanya karena token tidak kosong */
interface CaptchaVerifier
{
    /* Apakah provider benar-benar terkonfigurasi dan dapat dipakai? */
    public function isConfigured(): bool;

    /* Verifikasi token ke provider. False = tolak, jangan pernah "fallback sukses" */
    public function verify(string $token, Request $request): bool;

    /* Alasan singkat untuk ditampilkan di laporan status integrasi */
    public function status(): string;
}
