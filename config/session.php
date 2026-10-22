<?php

use Illuminate\Support\Str;

return [
    'driver' => env('SESSION_DRIVER', 'file'),

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    // Equipos compartidos en las estaciones: la sesión termina también al cerrar el navegador
    // (quien marcó "Recordarme" vuelve a entrar con su cookie).
    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', true),

    'encrypt' => env('SESSION_ENCRYPT', false),

    'files' => storage_path('framework/sessions'),

    'connection' => env('SESSION_CONNECTION'),

    'table' => env('SESSION_TABLE', 'sessions'),

    'store' => env('SESSION_STORE'),

    'lottery' => [2, 100],

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    'path' => env('SESSION_PATH', '/'),

    'domain' => env('SESSION_DOMAIN'),

    // En producción la cookie de sesión solo viaja por HTTPS.
    'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production'),

    'http_only' => env('SESSION_HTTP_ONLY', true),

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

    'serialization' => 'json',
];
