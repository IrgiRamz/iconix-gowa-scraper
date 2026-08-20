<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GoWA Server Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk koneksi ke server GoWA di VPS.
    | Semua pengaturan ini dapat di-override melalui environment variables.
    |
    */

    'base_url' => env('GOWA_BASE_URL', 'http://127.0.0.1:3000'),

    'basic_auth_user' => env('GOWA_BASIC_AUTH_USER', ''),

    'basic_auth_pass' => env('GOWA_BASIC_AUTH_PASS', ''),

    'timeout' => env('GOWA_TIMEOUT', 15),

    'connect_timeout' => env('GOWA_CONNECT_TIMEOUT', 5),
];
