<?php

use Illuminate\Support\Str;

return [
    // Cookie sessions avoid database tables and writable session directories.
    'driver' => 'cookie',
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => true,
    'files' => storage_path('framework/sessions'),
    'connection' => null,
    'table' => 'sessions',
    'store' => null,
    'lottery' => [0, 100],
    'cookie' => env('SESSION_COOKIE', Str::slug((string) env('APP_NAME', 'laravel')).'-session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => true,
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
];
