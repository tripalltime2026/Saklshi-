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
    'session.driver' => 'array',
    'cache.default' => 'array',
    'saklshi.sms.driver' => 'log',
]);

Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

function customer_check(bool $condition, string $label): void {
    if (! $condition) throw new RuntimeException($label);
    echo "PASS: {$label}\n";
}

$session = app('session')->driver();
$session->start();
function customer_req(string $uri, string $method = 'GET', array $data = []): Illuminate\Http\Request {
    global $session;
    $request = Illuminate\Http\Request::create($uri, $method, $data);
    $request->setLaravelSession($session);
    return $request;
}

$auth = new App\Http\Controllers\CustomerAuthController;
$middleware = new App\Http\Middleware\CustomerAuthenticate;

customer_check(
    $middleware->handle(customer_req('/account'), fn () => response('private'))->getStatusCode() === 302,
    'Customer account requires verified session'
);

$auth->requestCode(customer_req('/account/code', 'POST', ['phone' => '555 12 34 56']), app(App\Services\SmsSender::class));
$pendingPhone = $session->get('saklshi_pending_phone');
customer_check($pendingPhone === '+995555123456', 'Georgian mobile number is normalized');

$otp = Illuminate\Support\Facades\DB::table('phone_verification_codes')->latest('id')->first();
customer_check($otp && $otp->code_hash !== '123456', 'OTP is never stored as plaintext');

Illuminate\Support\Facades\DB::table('phone_verification_codes')->where('id', $otp->id)->update([
    'code_hash' => Illuminate\Support\Facades\Hash::make('123456'),
]);

$branchId = App\Models\Branch::where('slug', 'batumi')->value('id');
App\Models\Reservation::create([
    'branch_id' => $branchId,
    'reference' => 'HIST0001',
    'visit_date' => now('Asia/Tbilisi')->subDay()->toDateString(),
    'start_minute' => 840,
    'end_minute' => 960,
    'capacity_end_minute' => 960,
    'dining_table_id' => null,
    'guests' => 2,
    'occasion' => 'friends',
    'first_name' => 'Old',
    'last_name' => 'Guest',
    'phone' => '+995555123456',
    'birth_day' => 2,
    'birth_month' => 2,
    'birth_year' => 1996,
    'source' => 'Website',
    'status' => 'completed',
]);

try {
    $auth->verify(customer_req('/account/verify', 'POST', ['code' => '000000']));
    throw new RuntimeException('Wrong OTP was accepted');
} catch (Illuminate\Validation\ValidationException $e) {
}
customer_check((int) Illuminate\Support\Facades\DB::table('phone_verification_codes')->where('id', $otp->id)->value('attempts') === 1, 'Wrong OTP increments attempts');

$auth->verify(customer_req('/account/verify', 'POST', ['code' => '123456']));
$user = App\Models\User::where('phone', '+995555123456')->firstOrFail();
customer_check((int) $session->get('saklshi_customer_id') === $user->id, 'Successful OTP creates authenticated customer session');
customer_check($user->phone_verified_at !== null, 'Phone is marked verified');
customer_check($user->profile()->firstOrFail()->first_name === 'Old', 'Verified phone imports profile details from existing reservation');
customer_check((int) App\Models\Reservation::where('reference', 'HIST0001')->value('user_id') === $user->id, 'Historical reservations are claimed only after phone verification');
customer_check(
    $middleware->handle(customer_req('/account'), fn () => response('private'))->getStatusCode() === 200,
    'Verified customer can access protected account'
);

App\Models\BookingSettings::where('branch_id', $branchId)->update(['capacity' => 10, 'max_party_size' => 10, 'active' => true]);
$booking = new App\Http\Controllers\ReservationController;
$date = now('Asia/Tbilisi')->addDay()->toDateString();
$booking->store(customer_req('/reservations', 'POST', [
    'visit_date' => $date,
    'visit_time' => '14:00',
    'guests' => 3,
    'occasion' => 'friends',
    'first_name' => 'Changed',
    'last_name' => 'Input',
    'phone' => '+995599999999',
    'birth_date' => '1996-02-02',
]));

$newReservation = App\Models\Reservation::latest('id')->firstOrFail();
customer_check((int) $newReservation->user_id === $user->id, 'New booking is linked to authenticated customer');
customer_check($newReservation->phone === $user->phone, 'Authenticated booking uses verified phone instead of editable phone input');
customer_check((int) $newReservation->branch_id === (int) $branchId, 'New customer booking belongs to the active branch');

$account = new App\Http\Controllers\CustomerAccountController;
$request = customer_req('/account');
$request->attributes->set('customer', $user);
$data = $account->dashboard($request)->getData();
customer_check($data['reservations']->total() === 2, 'Account dashboard shows own historical and new reservations');

$profileRequest = customer_req('/account/profile', 'PUT', [
    'first_name' => 'Toko',
    'last_name' => 'Guest',
    'email' => 'customer@example.test',
    'birth_date' => '1996-02-02',
    'locale' => 'ka',
    'marketing_consent' => 1,
]);
$profileRequest->attributes->set('customer', $user);
$account->update($profileRequest);
customer_check($user->fresh()->profile->first_name === 'Toko', 'Customer can update profile');
customer_check($user->fresh()->email === 'customer@example.test', 'Customer can add email');

$auth->logout(customer_req('/account/logout', 'POST'));
customer_check(! $session->has('saklshi_customer_id'), 'Logout clears customer session');

echo "PASS: Customer OTP and account flow is ready.\n";
