<?php

use App\Support\PasswordHashPolicy;

/*
 Konfigurasi hashing kata sandi (PRD 20.3)
*/

return [

    /*
     Algoritma hashing
    */

    'driver' => 'bcrypt',

    'bcrypt' => [
        'rounds' => PasswordHashPolicy::resolveRounds(env('BCRYPT_ROUNDS')),
        'verify' => env('HASH_VERIFY', true),
        'limit' => env('BCRYPT_LIMIT'),
    ],

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],

    /*
     Rehash saat login
    */

    'rehash_on_login' => true,

];
