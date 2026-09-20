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
App\Models\BookingSettings::findOrFail(1)->update(['capacity' => 2]);
$controller = new App\Http\Controllers\ReservationController;
$date = now('Asia/Tbilisi')->addDay()->toDateString();
$available = fn (int $start) => $controller->availability(req('/api/availability', 'GET', ['date' => $date, 'start' => $start, 'guests' => 2]))->getData(true);
check($available(840)['available'] === 1 && $available(840)['free_seats'] === 2, 'Initial guest capacity without dining tables');
$data = ['visit_date' => $date, 'visit_time' => '14:00', 'guests' => 2, 'occasion' => 'friends', 'first_name' => 'Test', 'last_name' => 'Guest', 'phone' => '+995555000001', 'birth_date' => '1990-01-01'];
$controller->store(req('/reservations', 'POST', $data));
check(App\Models\Reservation::count() === 1, 'Reservation is persisted');
check($available(840)['available'] === 0 && $available(840)['free_seats'] === 0, 'Reservation subtracts guests');
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

$admin->storeMenu(req('/admin/menu', 'POST', ['name' => 'Test cold dish', 'category' => 'ცივი კერძები', 'price_gel' => '12.50', 'active' => 1]));
$dish = App\Models\MenuItem::where('name', 'Test cold dish')->firstOrFail();
check($dish->price === 1250 || (int) $dish->price === 1250, 'Menu prices stored in tetri');
check($controller->index()->getData()['menu']->contains('id', $dish->id), 'Added active dish appears for guests');
$admin->updateMenu(req('/admin/menu', 'PUT', ['name' => 'Test hot dish', 'category' => 'ცხელი კერძები', 'price_gel' => '18.25', 'active' => 1]), $dish);
check($dish->fresh()->category === 'ცხელი კერძები' && (int) $dish->fresh()->price === 1825, 'Dish name, price and category can be edited');
$admin->toggleMenu($dish->fresh());
check(! $controller->index()->getData()['menu']->contains('id', $dish->id), 'Hidden dish is excluded from guest menu');
$admin->toggleMenu($dish->fresh());
$admin->updateMenu(req('/admin/menu', 'PUT', ['name' => 'Test hot dish', 'category' => 'ცხელი კერძები', 'custom_category' => 'საფირმო კერძები', 'price_gel' => '18.25', 'active' => 1]), $dish->fresh());
check($dish->fresh()->category === 'საფირმო კერძები', 'Custom categories supported');
$html = $admin->index(req('/admin'))->render();
check(str_contains($html, 'საფირმო კერძები') && str_contains($html, 'კერძის წაშლა'), 'Grouped menu management renders');
$second->items()->create(['menu_item_id' => $dish->id, 'name' => 'Test hot dish', 'unit_price' => 1825, 'quantity' => 2]);
$admin->destroyMenu($dish->fresh());
check(! App\Models\MenuItem::whereKey($dish->id)->exists(), 'Dish is deleted');
$line = $second->items()->first();
check($line && $line->menu_item_id === null && $line->name === 'Test hot dish' && (int) $line->unit_price === 1825 && (int) $line->quantity === 2, 'Deletion preserves historical order details');
check(! $controller->index()->getData()['menu']->contains('id', $dish->id), 'Deleted dish unavailable for new reservations');

// Photo import, fractional prices and admin edits must survive repeat deployments.
check(App\Models\MenuItem::whereNotNull('source_key')->count() === 122, 'All photo menu positions imported');
$khinkali = App\Models\MenuItem::where('name', 'ქალაქური')->firstOrFail();
check($khinkali->price === 240 && $khinkali->category === 'ხინკალი', 'Khinkali price is 2.40 GEL');
check(App\Models\MenuItem::where('name', 'წყალი')->firstOrFail()->price === 300, 'Water price matches photo');
$khinkali->update(['price' => 250, 'active' => false]);
(new Database\Seeders\PhotoMenuSeeder)->run();
check($khinkali->fresh()->price === 250 && ! $khinkali->fresh()->active, 'Repeat import preserves admin price and visibility');
$khinkali->update(['price' => 240, 'active' => true]);
$guestHtml = $controller->index()->render();
check(str_contains($guestHtml, 'data-menu-category') && str_contains($guestHtml, 'data-menu-search'), 'Guest menu renders category selection and search');
check(str_contains($guestHtml, '2.40') && str_contains($guestHtml, 'data-menu-pagination'), 'Fractional prices and pagination render');
// More than 30 selected positions must not be silently dropped.
$ordered = App\Models\MenuItem::where('active', true)->limit(31)->get();
$menuBooking = $data;
$menuBooking['visit_time'] = '18:00';
$menuBooking['items'] = $ordered->mapWithKeys(fn ($item) => [$item->id => 1])->all();
$controller->store(req('/reservations', 'POST', $menuBooking));
$menuReservation = App\Models\Reservation::latest('id')->firstOrFail();
check($menuReservation->items()->count() === 31, 'All 31 selected positions persist');
check((int) $menuReservation->items()->sum('unit_price') === (int) $ordered->sum('price'), 'Order uses exact server-side menu prices');
$historical = $menuReservation->items()->firstOrFail();
App\Models\MenuItem::findOrFail($historical->menu_item_id)->update(['price' => 9999]);
check($historical->fresh()->unit_price === $historical->unit_price, 'Admin price changes preserve existing booking prices');

$auth->logout(req('/admin/logout', 'POST'));
check($guard->handle(req('/admin'), fn () => response('protected'))->getStatusCode() === 302, 'Logout protects admin again');


foreach (['2026-09-09 08:10:00' => ['2026-09-09', '12:00'], '2026-09-09 19:15:00' => ['2026-09-09', '20:00'], '2026-09-09 22:10:00' => ['2026-09-10', '12:00'], '2026-09-09 23:40:00' => ['2026-09-10', '12:00']] as $clock => [$expectedDate, $expectedTime]) {
    Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse($clock, 'Asia/Tbilisi'));
    $defaults = $controller->index()->getData();
    check($defaults['defaultVisitDate'] === $expectedDate && $defaults['defaultVisitTime'] === $expectedTime, 'Booking opens on a future slot at '.$clock);
}
Illuminate\Support\Carbon::setTestNow();

// Operational dashboard and complete exports use the same committed reservation/order data.
$ops = new App\Http\Controllers\AdminController;
$opsData = $ops->index(req('/admin'))->getData();
check($opsData['allDates'] && $opsData['reservations']->contains('id', $menuReservation->id), 'Default dashboard includes future bookings');
$opsHtml = $ops->index(req('/admin'))->render();
check(str_contains($opsHtml, 'data-order-detail="'.$menuReservation->id.'"'), 'Ordered dishes are visible in the booking list');
check($menuReservation->dining_table_id === null && str_contains($opsHtml, 'სტუმრების რაოდენობით'), 'Booking has no table assignment');
$live = $ops->live(req('/admin/live'));
check($live->getStatusCode() === 200 && str_contains($live->getContent(), $menuReservation->reference), 'Live refresh includes newly committed booking');
$exports = new App\Http\Controllers\AdminExportController;
function exportText($response): string { ob_start(); $response->sendContent(); return ob_get_clean(); }
$csv = exportText($exports->download(req('/admin/export/reservations', 'GET', ['q' => $menuReservation->reference]), 'reservations'));
check(str_contains($csv, $menuReservation->reference) && str_contains($csv, $historical->name), 'Booking CSV includes menu and reference');
$csv = exportText($exports->download(req('/admin/export/orders', 'GET', ['q' => $menuReservation->reference]), 'orders'));
check(substr_count($csv, $menuReservation->reference) === 31, 'Order CSV exports every ordered position');
$json = json_decode(exportText($exports->download(req('/admin/export/backup'), 'backup')), true, 512, JSON_THROW_ON_ERROR);
check(count($json['tables']['reservations']) === App\Models\Reservation::count(), 'Full JSON includes all reservations');
check(count($json['tables']['reservation_items']) === App\Models\ReservationItem::count(), 'Full JSON includes complete order snapshots');
check(! isset($json['tables']['sessions']), 'Operational export excludes authentication data');
$csv = exportText($exports->download(req('/admin/export/menu'), 'menu'));
check(str_starts_with($csv, "\xEF\xBB\xBF") && str_contains($csv, 'ქალაქური'), 'CSV has Georgian Excel-compatible encoding');
$beforeMismatch = App\Models\Reservation::count();
try {
    $controller->store(req('/reservations', 'POST', $data + ['menu_quantity' => 2]));
    throw new RuntimeException('Incomplete preorder accepted');
} catch (Illuminate\Validation\ValidationException $e) {}
check(App\Models\Reservation::count() === $beforeMismatch, 'Missing preorder payload cannot silently create an empty order');
foreach (['reservations', 'orders', 'guests', 'menu', 'backup'] as $type) {
    check($guard->handle(req('/admin/export/'.$type), fn () => response('private'))->getStatusCode() === 302, 'Unauthenticated '.$type.' export blocked');
}

// Capacity is shared by guests, not constrained by individual table sizes.
App\Models\BookingSettings::findOrFail(1)->update(['capacity' => 10, 'max_party_size' => 10]);
$party = $data;
$party['visit_date'] = now('Asia/Tbilisi')->addDays(3)->toDateString();
$party['guests'] = 6;
$controller->store(req('/reservations', 'POST', $party + ['dining_table_id' => 999]));
$large = App\Models\Reservation::latest('id')->firstOrFail();
check($large->guests === 6 && $large->dining_table_id === null && App\Models\BookingSlot::count() === 0, 'Large group books without tables or table slots');
$party['guests'] = 4;
$controller->store(req('/reservations', 'POST', $party));
$capacity = app(App\Services\GuestCapacity::class);
check($capacity->remaining($party['visit_date'], 840, 960, $capacity->settings()) === 0, 'Two parties share all ten seats');
try {
    $controller->store(req('/reservations', 'POST', array_replace($party, ['guests' => 1])));
    throw new RuntimeException('Over capacity accepted');
} catch (Illuminate\Validation\ValidationException $e) {}
$settingsData = ['capacity' => 9, 'max_party_size' => 10, 'duration_minutes' => 120, 'buffer_minutes' => 30, 'active' => 1];
try {
    $admin->updateBookingSettings(req('/admin/booking-settings', 'PUT', $settingsData));
    throw new RuntimeException('Capacity reduced below committed guests');
} catch (Illuminate\Validation\ValidationException $e) {}
check($capacity->settings()->capacity === 10, 'Unsafe capacity reduction is rolled back');
$admin->updateBookingSettings(req('/admin/booking-settings', 'PUT', array_replace($settingsData, ['capacity' => 10])));
check(Illuminate\Support\Facades\DB::table('booking_audit_logs')->count() === 1, 'Settings changes have an audit trail');
check($large->fresh()->capacity_end_minute === 960, 'New buffer does not rewrite previous reservations');
$party['visit_date'] = now('Asia/Tbilisi')->addDays(4)->toDateString();
$controller->store(req('/reservations', 'POST', array_replace($party, ['guests' => 10])));
check($capacity->remaining($party['visit_date'], 960, 1080, $capacity->settings()) === 0, 'Buffer blocks seats after visit ends');
check($capacity->remaining($party['visit_date'], 990, 1110, $capacity->settings()) === 10, 'Seats released exactly at buffer boundary');
$admin->updateBookingSettings(req('/admin/booking-settings', 'PUT', array_replace($settingsData, ['capacity' => 10, 'active' => 0])));
$closed = $controller->availability(req('/api/availability', 'GET', ['date' => $party['visit_date'], 'start' => 1080, 'guests' => 1]))->getData(true);
check($closed['available'] === 0 && !$closed['booking_open'], 'Paused restaurant cannot accept reservations');
try {
    $controller->store(req('/reservations', 'POST', array_replace($party, ['visit_time' => '18:00'])));
    throw new RuntimeException('Paused booking accepted');
} catch (Illuminate\Validation\ValidationException $e) {}
check(Illuminate\Support\Facades\DB::table('reservation_status_history')->where('reservation_id', $reservation->id)->count() === 2, 'Initial and cancellation status recorded');
check($capacity->peak([
    (object) ['start_minute' => 720, 'capacity_end_minute' => 840, 'guests' => 6],
    (object) ['start_minute' => 840, 'capacity_end_minute' => 960, 'guests' => 7],
], 780, 900) === 7, 'Adjacent bookings are not incorrectly summed');
