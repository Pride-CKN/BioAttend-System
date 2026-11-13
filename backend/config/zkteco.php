<?php

return [

    'driver' => env('ZKTECO_DRIVER', 'sdk'),

    /*
    |--------------------------------------------------------------------------
    | Shared connection defaults
    |--------------------------------------------------------------------------
    | IPs are NOT stored here: each device has its own IP in the `devices`
    | table (5 devices = 5 rows). Only values shared by all devices live here.
    | 4370 is the ZKTeco default port.
    */
    'port' => (int) env('ZKTECO_PORT', 4370),

    // Fallback commkey only if a device row has none (0 = ZKTeco factory default).
    // Real per-device commkeys live encrypted in devices.commkey_encrypted.
    'default_commkey' => env('ZKTECO_COMMKEY', 0),

    /*
    |--------------------------------------------------------------------------
    | Pull behaviour
    |--------------------------------------------------------------------------
    */

    'timeout_seconds' => (int) env('ZKTECO_TIMEOUT', 30),

    'retries' => (int) env('ZKTECO_RETRIES', 3),

    // Queue used by DeviceSyncJob (the controller reads this too).
    'queue' => env('ZKTECO_QUEUE', 'device-sync'),

    // Queue used by employee provisioning jobs. Kept separate from attendance
    // pulls so a slow device-user update cannot delay log ingestion.
    'employee_push_queue' => env('ZKTECO_EMPLOYEE_PUSH_QUEUE', 'device-push'),

    // Seconds to wait between retries (attempt 1, 2, 3).
    'backoff_seconds' => [10, 30, 60],

];
