<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! $app->environment('testing')) {
    throw new RuntimeException('Run only in the testing environment.');
}

config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => ':memory:',
    'cache.default' => 'array',
    'session.driver' => 'array',
]);

Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

function foundation_check(bool $condition, string $label): void {
    if (! $condition) throw new RuntimeException($label);
    echo "PASS: {$label}\n";
}

$branch = App\Models\Branch::where('slug', 'batumi')->firstOrFail();
foundation_check($branch->name === 'ბათუმის სახლი', 'Default Batumi branch exists');
foundation_check($branch->address === 'ფარნავაზ მეფის 69', 'Default branch keeps the known Batumi address');
foundation_check(app(App\Services\BranchContext::class)->id() === $branch->id, 'Default branch context resolves Batumi');

$settings = App\Models\BookingSettings::findOrFail(1);
foundation_check((int) $settings->branch_id === (int) $branch->id, 'Existing booking settings belong to Batumi');
foundation_check(App\Models\MenuItem::where('branch_id', $branch->id)->count() === 122, 'Imported menu belongs to Batumi');

$settings->update(['capacity' => 5, 'max_party_size' => 5]);
$session = app('session')->driver();
$session->start();
$request = Illuminate\Http\Request::create('/reservations', 'POST', [
    'visit_date' => now('Asia/Tbilisi')->addDay()->toDateString(),
    'visit_time' => '14:00',
    'guests' => 2,
    'occasion' => 'friends',
    'first_name' => 'Foundation',
    'last_name' => 'Guest',
    'phone' => '+995555111222',
    'birth_date' => '1990-01-01',
]);
$request->setLaravelSession($session);
(new App\Http\Controllers\ReservationController)->store($request);
$reservation = App\Models\Reservation::latest('id')->firstOrFail();
foundation_check((int) $reservation->branch_id === (int) $branch->id, 'New reservations default to Batumi');

$other = App\Models\Branch::create([
    'name' => 'Test Branch',
    'slug' => 'test-branch',
    'city' => 'Test',
    'timezone' => 'Asia/Tbilisi',
    'active' => true,
]);
$otherSettings = App\Models\BookingSettings::create([
    'branch_id' => $other->id,
    'capacity' => 10,
    'max_party_size' => 10,
    'duration_minutes' => 120,
    'buffer_minutes' => 0,
    'active' => true,
]);
App\Models\Reservation::create([
    'branch_id' => $other->id,
    'reference' => 'BRANCH02',
    'visit_date' => $reservation->visit_date->format('Y-m-d'),
    'start_minute' => 840,
    'end_minute' => 960,
    'capacity_end_minute' => 960,
    'dining_table_id' => null,
    'guests' => 10,
    'occasion' => 'friends',
    'first_name' => 'Other',
    'last_name' => 'Branch',
    'phone' => '+995555999999',
    'birth_day' => 1,
    'birth_month' => 1,
    'birth_year' => 1990,
    'source' => 'Test',
    'status' => 'confirmed',
]);
$capacity = app(App\Services\GuestCapacity::class);
foundation_check($capacity->remaining($reservation->visit_date->format('Y-m-d'), 840, 960, $settings) === 3, 'Capacity is isolated by branch');
foundation_check($capacity->remaining($reservation->visit_date->format('Y-m-d'), 840, 960, $otherSettings) === 0, 'Second branch has independent capacity');

$user = App\Models\User::create([
    'phone' => '+995555123123',
    'email' => 'foundation@example.test',
    'password' => 'test-password',
    'phone_verified_at' => now(),
]);
$user->profile()->create([
    'first_name' => 'Test',
    'last_name' => 'User',
    'birth_date' => '1996-02-02',
]);
foundation_check(Illuminate\Support\Facades\Hash::check('test-password', $user->fresh()->password), 'User password is hashed');
foundation_check($user->profile()->firstOrFail()->birth_date->format('Y-m-d') === '1996-02-02', 'User profile persists separately');

Illuminate\Support\Facades\DB::table('phone_verification_codes')->insert([
    'phone' => $user->phone,
    'purpose' => 'login',
    'code_hash' => Illuminate\Support\Facades\Hash::make('123456'),
    'expires_at' => now()->addMinutes(5),
    'created_at' => now(),
    'updated_at' => now(),
]);
$otp = Illuminate\Support\Facades\DB::table('phone_verification_codes')->first();
foundation_check($otp->code_hash !== '123456' && Illuminate\Support\Facades\Hash::check('123456', $otp->code_hash), 'OTP storage is hash-only');

$branchesResponse = (new App\Http\Controllers\BranchController)->index()->getData(true);
foundation_check(count($branchesResponse['data']) === 2, 'Public branch endpoint returns active branches');

echo "PASS: Platform foundation is ready for branch routing, OTP auth and account UI.\n";
