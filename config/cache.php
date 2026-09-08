<?php

return [
    // Production-safe for this single-instance app. Do not let a Cloud-level
    // CACHE_STORE=database override this until database cache tables exist.
    'default' => 'file',

    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'saklshi_cache_'),
];
