<?php

/*
|--------------------------------------------------------------------------
| CAPTCHA / anti-bruteforce lanjutan (PRD 20.1)
|--------------------------------------------------------------------------
|
| STATUS DEFAULT: BLOCKED. Tidak ada provider CAPTCHA yang terpasang, tidak
| ada secret hardcoded, dan tidak ada verifikasi palsu yang selalu "sukses".
| Bila `driver = none`, login yang melewati ambang kegagalan DITOLAK dengan
| pesan jujur, bukan dilewati diam-diam.
|
| Untuk mengaktifkan: pasang adapter yang mengimplementasikan CaptchaVerifier,
| set CAPTCHA_DRIVER, CAPTCHA_VERIFY_URL, dan CAPTCHA_SECRET lewat secret
| manager (jangan pernah commit nilainya).
*/
return [
    'driver' => env('CAPTCHA_DRIVER', 'none'),

    'trigger_after_failures' => (int) env('CAPTCHA_TRIGGER_AFTER_FAILURES', 3),

    'verify_url' => env('CAPTCHA_VERIFY_URL'),

    'secret' => env('CAPTCHA_SECRET'),
];
