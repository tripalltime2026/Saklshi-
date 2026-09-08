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
    'saklshi.admin_login' => 'test-admin',
    'saklshi.admin_password' => 'test-only-password',
]);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$session = app('session')->driver();
$session->start();
function check(bool $condition, string $label): void {
    if (! $condition) throw new RuntimeException($label);
    echo "PASS: ".$label.PHP_EOL;
}
function req(string $uri, string $method = 'GET', array $data = []): Illuminate\Http\Request {
    global $session;
    $r = Illuminate\Http\Request::create($uri, $method, $data);
    $r->setLaravelSession($session);
    return $r;
}
$auth = new App\Http\Controllers\AdminAuthController;
$guard = new App\Http\Middleware\AdminAuthenticate;
check($guard->handle(req('/admin'), fn () => response('protected'))->getStatusCode() === 302, 'Guest cannot open admin');
try {
    $auth->login(req('/admin/login', 'POST', ['login' => 'test-admin', 'password' => 'wrong']));
    throw new RuntimeException('Wrong password accepted');
} catch (Illuminate\Validation\ValidationException $e) {}
check(! $session->get('saklshi_admin'), 'Wrong password does not authenticate');
$auth->login(req('/admin/login', 'POST', ['login' => 'test-admin', 'password' => 'test-only-password']));
check($guard->handle(req('/admin'), fn () => response('protected'))->getStatusCode() === 200, 'Correct login grants access');
$session->put('saklshi_admin_login_at', time() - 28801);
check($guard->handle(req('/admin'), fn () => response('protected'))->getStatusCode() === 302, 'Expired login requires password again');
$auth->login(req('/admin/login', 'POST', ['login' => 'test-admin', 'password' => 'test-only-password']));
App\Models\DiningTable::create(['name' => 'Test table', 'capacity' => 4, 'x' => 40, 'y' => 40, 'active' => true]);
$controller = new App\Http\Controllers\ReservationController;
$date = now('Asia/Tbilisi')->addDay()->toDateString();
$available = fn (int $start) => $controller->availability(req('/api/availability', 'GET', ['date' => $date, 'start' => $start, 'guests' => 2]))->getData(true);
check($available(840)['available'] === 1 && $available(840)['free_seats'] === 4, 'Initial table capacity');
$data = ['visit_date' => $date, 'visit_time' => '14:00', 'guests' => 2, 'occasion' => 'friends', 'first_name' => 'Test', 'last_name' => 'Guest', 'phone' => '+995555000001', 'birth_date' => '1990-01-01'];
$controller->store(req('/reservations', 'POST', $data));
check(App\Models\Reservation::count() === 1, 'Reservation is persisted');
check($available(840)['available'] === 0 && $available(840)['free_seats'] === 0, 'Reservation subtracts table and seats');
check($available(900)['available'] === 0, 'Overlapping slot unavailable');
check($available(960)['available'] === 1, 'Next non-overlapping slot remains available');
try {
    $controller->store(req('/reservations', 'POST', $data));
    throw new RuntimeException('Double booking accepted');
} catch (Illuminate\Validation\ValidationException $e) {}
check(App\Models\Reservation::count() === 1, 'Rejected booking creates no duplicate');
$admin = new App\Http\Controllers\AdminController;
$reservation = App\Models\Reservation::first();
$view = $admin->index(req('/admin', 'GET', ['date' => $date, 'start' => 840]));
check($view->getData()['freeSeats'] === 0, 'Admin capacity matches guest capacity');
view()->share('errors', new Illuminate\Support\ViewErrorBag);
check(str_contains($view->render(), 'data-free-capacity'), 'Populated admin dashboard renders');
$admin->updateStatus(req('/admin/status', 'PATCH', ['status' => 'cancelled']), $reservation);
check($available(840)['available'] === 1, 'Cancellation releases capacity');
try {
    $admin->updateStatus(req('/admin/status', 'PATCH', ['status' => 'confirmed']), $reservation);
    throw new RuntimeException('Cancelled booking reactivated without slots');
} catch (Illuminate\Validation\ValidationException $e) {}
$controller->store(req('/reservations', 'POST', $data));
$second = App\Models\Reservation::latest('id')->first();
$admin->updateStatus(req('/admin/status', 'PATCH', ['status' => 'arrived']), $second);
$admin->updateStatus(req('/admin/status', 'PATCH', ['status' => 'completed']), $second);
check($available(840)['available'] === 1, 'Completion releases capacity');
$filtered = $admin->index(req('/admin', 'GET', ['scope' => 'all', 'status' => 'cancelled']));
check($filtered->getData()['reservations']->count() === 1, 'All dates and status filters work');
$auth->logout(req('/admin/logout', 'POST'));
check($guard->handle(req('/admin'), fn () => response('protected'))->getStatusCode() === 302, 'Logout protects admin again');
