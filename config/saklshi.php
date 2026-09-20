<?php

return [
    'admin_login' => env('ADMIN_LOGIN', 'admin'),
    'admin_password' => env('ADMIN_PASSWORD'),
    'default_branch_slug' => env('DEFAULT_BRANCH_SLUG', 'batumi'),
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'key' => env('SMSOFFICE_KEY'),
        'sender' => env('SMSOFFICE_SENDER', 'SAKHLSHI'),
    ],
];
