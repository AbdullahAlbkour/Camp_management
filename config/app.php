<?php

return [
    'name' => env('APP_NAME', 'Camps Management'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'Asia/Damascus',
    'locale' => env('APP_LOCALE', 'ar'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'ar'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'ar_SA'),
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'maintenance' => [
        'driver' => 'file',
        'store' => 'database',
    ],
];
