<?php

return [
    // Stateless cache avoids any dependency on a database table or writable disk.
    'default' => 'array',

    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'saklshi_cache_'),
];
