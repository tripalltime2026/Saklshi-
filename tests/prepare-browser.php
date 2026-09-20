<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing')) throw new RuntimeException('Testing only.');
App\Models\BookingSettings::findOrFail(1)->update(['capacity' => 40, 'max_party_size' => 20]);
