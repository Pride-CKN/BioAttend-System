<?php

namespace App\Providers;

use App\Services\Auth\Captcha\Contracts\CaptchaVerifier;
use App\Services\Auth\Captcha\NullCaptchaVerifier;
use App\Services\ZKTeco\Contracts\DeviceAdapter;
use App\Services\ZKTeco\MockDeviceAdapter;
use App\Services\ZKTeco\ZKTecoSdkAdapter;
use App\Support\LoginThrottle;
use App\Support\PasswordHashPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Binding adapter perangkat: vendor SDK dipisahkan dari business logic
        $this->app->singleton(DeviceAdapter::class, function ($app) {
            $driver = config('zkteco.driver', 'sdk');

            if ($driver === 'mock') {
                // Mock hanya boleh dipakai di lingkungan testing/local
                // Produksi tidak boleh mengklaim koneksi nyata lewat mock
                if ($app->environment('production')) {
                    throw new \RuntimeException(
                        'ZKTECO_DRIVER=mock tidak diizinkan di production.'
                    );
                }

                return new MockDeviceAdapter();
            }

            return new ZKTecoSdkAdapter();
        });

        // CAPTCHA: default NOT_CONFIGURED (BLOCKED)
        // didaftarkan di sini setelah provider & secret tersedia
        $this->app->bind(CaptchaVerifier::class, fn () => new NullCaptchaVerifier());
    }

    public function boot(): void
    {
        // konfigurasi hashing wajib bcrypt dengan cost >= 10
        // Konfigurasi lemah dihentikan
        PasswordHashPolicy::assertConfiguration();

        //  5 percobaan login / 15 menit per (email + IP)
        RateLimiter::for('login', fn (Request $request) => Limit::perMinutes(15, 5)
            ->by(LoginThrottle::key($request)));

        // Pembatasan umum 60 permintaan / menit untuk seluruh API terautentikasi
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
