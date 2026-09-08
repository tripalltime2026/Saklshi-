<?php

return [
    // No database queue table is required for the reservation/admin flow.
    'default' => 'sync',

    'connections' => [
        'sync' => ['driver' => 'sync'],
    ],

    'failed' => [
        'driver' => 'null',
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
