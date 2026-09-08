<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Pin infrastructure-free stores at runtime so Laravel Cloud injected
        // variables cannot switch cache/session back to database-backed drivers.
        config([
            'cache.default' => 'array',
            'session.driver' => 'cookie',
            'session.encrypt' => true,
            'queue.default' => 'sync',
        ]);

        date_default_timezone_set(config('app.timezone', 'Asia/Tbilisi'));
    }
}
