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
        // Laravel Cloud may inject database-backed cache/session defaults.
        // This app does not require those database infrastructure tables,
        // so pin these stores at runtime to prevent 500 errors.
        config([
            'cache.default' => 'file',
            'session.driver' => 'file',
            'queue.default' => 'sync',
        ]);

        date_default_timezone_set(config('app.timezone', 'Asia/Tbilisi'));
    }
}
