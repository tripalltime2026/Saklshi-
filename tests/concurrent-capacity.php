<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql') {
    throw new RuntimeException('Requires a disposable testing MySQL database.');
}
config(['session.driver' => 'array', 'cache.default' => 'array']);
$date = now('Asia/Tbilisi')->addDays(60)->toDateString();

if (($argv[1] ?? '') === 'worker') {
    while (microtime(true) < (float) $argv[2]) usleep(1000);
    $session = app('session')->driver();
    $session->start();
    $request = Illuminate\Http\Request::create('/reservations', 'POST', [
        'visit_date' => $date, 'visit_time' => '14:00', 'guests' => 3,
        'occasion' => 'friends', 'first_name' => 'Concurrent', 'last_name' => 'Guest',
        'phone' => '+995555000001', 'birth_date' => '1990-01-01',
    ]);
    $request->setLaravelSession($session);
    try {
        $response = (new App\Http\Controllers\ReservationController)->store($request);
        echo str_contains($response->getTargetUrl(), '/reservation/') ? 'created' : 'error';
    } catch (Illuminate\Validation\ValidationException $e) {
        echo 'full';
    }
    exit;
}

if (App\Models\Reservation::whereDate('visit_date', $date)->exists()) {
    throw new RuntimeException('Concurrent test requires an unused test date.');
}
App\Models\BookingSettings::findOrFail(1)->update(['capacity' => 10, 'max_party_size' => 10]);
$processes = [];
$barrier = (string) (microtime(true) + 2);
for ($i = 0; $i < 8; $i++) {
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', $barrier], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (! is_resource($process)) throw new RuntimeException('Cannot start worker.');
    $processes[] = [$process, $pipes];
}
$results = [];
foreach ($processes as [$process, $pipes]) {
    $result = trim(stream_get_contents($pipes[1]));
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $errors !== '') throw new RuntimeException($errors);
    $results[] = $result;
}
$counts = array_count_values($results);
$guests = (int) App\Models\Reservation::whereDate('visit_date', $date)->sum('guests');
if (($counts['created'] ?? 0) !== 3 || ($counts['full'] ?? 0) !== 5 || $guests !== 9) {
    throw new RuntimeException(json_encode(['results' => $results, 'guests' => $guests]));
}
echo "PASS: Eight concurrent requests commit only three parties (9/10 seats), rejecting the other five.\n";
