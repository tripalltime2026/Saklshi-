<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing')) throw new RuntimeException('Testing only.');
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
Illuminate\Support\Facades\DB::purge('sqlite');
$paths = array_map(fn ($path) => 'database/migrations/'.basename($path), glob(__DIR__.'/../database/migrations/2026_09_0*.php'));
Illuminate\Support\Facades\Artisan::call('migrate', ['--path' => $paths, '--force' => true]);
$table = App\Models\DiningTable::create(['name' => 'Existing table', 'capacity' => 8, 'x' => 40, 'y' => 40, 'active' => true]);
$reservation = App\Models\Reservation::create([
    'reference' => 'LEGACY01', 'visit_date' => now()->addDay()->toDateString(),
    'start_minute' => 840, 'end_minute' => 960, 'dining_table_id' => $table->id, 'guests' => 3,
    'first_name' => 'Existing', 'last_name' => 'Guest', 'phone' => '+995555000000',
    'birth_day' => 1, 'birth_month' => 1, 'status' => 'confirmed',
]);
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$capacity = app(App\Services\GuestCapacity::class);
if ($capacity->settings()->capacity !== 8 || $reservation->fresh()->dining_table_id !== $table->id
    || $reservation->fresh()->capacity_end_minute !== 960
    || $capacity->remaining($reservation->visit_date->toDateString(), 840, 960, $capacity->settings()) !== 5) {
    throw new RuntimeException('Legacy migration changed data or lost occupancy.');
}
(new Database\Seeders\DatabaseSeeder)->run();
if ($capacity->settings()->capacity !== 8 || App\Models\DiningTable::count() !== 1) throw new RuntimeException('Seeder overwrote existing capacity.');
echo "PASS: Existing table references, visits and occupied guest capacity survive migration and reseeding.\n";
