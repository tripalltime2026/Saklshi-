<?php

namespace App\Http\Controllers;

use App\Services\GuestCapacity;
use Illuminate\Support\Facades\DB;
use App\Models\MenuItem;
use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class AdminController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'start' => ['nullable', 'integer', 'min:720', 'max:1320', 'multiple_of:30'],
            'status' => ['nullable', Rule::in(['confirmed', 'arrived', 'completed', 'cancelled', 'no_show'])],
            'scope' => ['nullable', Rule::in(['all', 'day'])],
        ]);
        $status = (string) $request->query('status', '');
        $allDates = $request->query('scope') !== 'day' && ! $request->filled('date');
        $mapStart = (int) $request->query('start', min(1320, max(720, (int) floor((now('Asia/Tbilisi')->hour * 60 + now('Asia/Tbilisi')->minute) / 30) * 30)));
        $freeSeats = 0;
        $query = trim((string) $request->query('q', ''));
        $date = (string) $request->query('date', '');
        $today = now('Asia/Tbilisi')->toDateString();
        $mapDate = $date !== '' ? $date : $today;
        $databaseReady = true;
        $databaseError = null;
        $bookingSettings = null;
        $settingsHistory = collect();

        try {
            $requiredTables = ['booking_settings', 'reservation_status_history', 'reservations', 'dining_tables', 'menu_items', 'reservation_items', 'booking_slots'];

            foreach ($requiredTables as $tableName) {
                if (! Schema::hasTable($tableName)) {
                    $databaseReady = false;
                    $databaseError = 'მონაცემთა ბაზის ცხრილები ჯერ არ არის შექმნილი.';
                    break;
                }
            }

            if ($databaseReady) {
                $reservations = Reservation::query()
                    ->with(['table', 'items', 'statusHistory'])
                    ->when($date !== '', fn ($q) => $q->whereDate('visit_date', $date))
                    ->when($date === '' && ! $allDates, fn ($q) => $q->whereDate('visit_date', $today))
                    ->when($status !== '', fn ($q) => $q->where('status', $status))
                    ->orderByDesc('created_at')->orderByDesc('id')
                    ->when($query !== '', function ($q) use ($query) {
                        $q->where(function ($inner) use ($query) {
                            $inner->where('first_name', 'like', "%{$query}%")
                                ->orWhere('last_name', 'like', "%{$query}%")
                                ->orWhere('phone', 'like', "%{$query}%")
                                ->orWhere('reference', 'like', "%{$query}%");
                        });
                    })
                    ->orderBy('start_minute')
                    ->paginate(50)->withPath(route('admin.dashboard'))->withQueryString();

                $latestGuestIds = Reservation::query()->selectRaw('MAX(id)')->groupBy('phone');
                $guestRows = Reservation::query()->whereIn('id', $latestGuestIds)->orderByDesc('id')->get();
                $visitCounts = Reservation::query()->whereIn('status', ['arrived', 'completed'])
                    ->selectRaw('phone, COUNT(*) as total')->groupBy('phone')->pluck('total', 'phone');
                $guests = $guestRows->map(fn ($latest) => (object) [
                    'first_name' => $latest->first_name, 'last_name' => $latest->last_name,
                    'phone' => $latest->phone, 'birth_day' => $latest->birth_day,
                    'birth_month' => $latest->birth_month, 'birth_year' => $latest->birth_year,
                    'marketing_consent' => $latest->marketing_consent, 'last_visit' => $latest->visit_date,
                    'visits' => (int) ($visitCounts[$latest->phone] ?? 0),
                ]);

                $menu = $request->attributes->get('live') ? collect() : MenuItem::query()->orderBy('category')->orderBy('sort_order')->orderBy('name')->get();

                $todayReservations = Reservation::query()
                    ->with('items')
                    ->whereDate('visit_date', $today)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->get();

                $todayCount = $todayReservations->count();
                $todayGuestCount = (int) $todayReservations->sum('guests');
                $restaurantCapacity = 0;
                $occupancyPercent = $restaurantCapacity > 0
                    ? min(100, (int) round(($todayGuestCount / $restaurantCapacity) * 100))
                    : 0;

                $todayPreorderRevenue = 0;
                foreach ($todayReservations as $reservation) {
                    foreach ($reservation->items as $item) {
                        $todayPreorderRevenue += ((int) $item->unit_price) * ((int) $item->quantity);
                    }
                }

                $capacity = app(GuestCapacity::class);
                $bookingSettings = $capacity->settings();
                $settingsHistory = DB::table('booking_audit_logs')->latest('id')->limit(20)->get();
                $restaurantCapacity = $bookingSettings->capacity;
                $freeSeats = $capacity->remaining($mapDate, $mapStart, $mapStart + $bookingSettings->duration_minutes + $bookingSettings->buffer_minutes, $bookingSettings);
                $occupancyPercent = $restaurantCapacity > 0
                    ? (int) round(100 * ($restaurantCapacity - $freeSeats) / $restaurantCapacity) : 0;

                $statusBreakdown = Reservation::query()
                    ->whereDate('visit_date', $today)
                    ->selectRaw('status, COUNT(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

                $todayByPeriod = [
                    'lunch' => [
                        'reservations' => $todayReservations->filter(fn ($r) => $r->start_minute < 960)->count(),
                        'guests' => (int) $todayReservations->filter(fn ($r) => $r->start_minute < 960)->sum('guests'),
                    ],
                    'early' => [
                        'reservations' => $todayReservations->filter(fn ($r) => $r->start_minute >= 960 && $r->start_minute < 1140)->count(),
                        'guests' => (int) $todayReservations->filter(fn ($r) => $r->start_minute >= 960 && $r->start_minute < 1140)->sum('guests'),
                    ],
                    'dinner' => [
                        'reservations' => $todayReservations->filter(fn ($r) => $r->start_minute >= 1140)->count(),
                        'guests' => (int) $todayReservations->filter(fn ($r) => $r->start_minute >= 1140)->sum('guests'),
                    ],
                ];
            } else {
                $reservations = collect();
                $guests = collect();
                $menu = collect();
                $todayReservations = collect();
                $todayCount = 0;
                $todayGuestCount = 0;
                $restaurantCapacity = 0;
                $occupancyPercent = 0;
                $todayPreorderRevenue = 0;
                $statusBreakdown = collect();
                $todayByPeriod = [
                    'lunch' => ['reservations' => 0, 'guests' => 0],
                    'early' => ['reservations' => 0, 'guests' => 0],
                    'dinner' => ['reservations' => 0, 'guests' => 0],
                ];
            }
        } catch (Throwable $e) {
            report($e);

            $databaseReady = false;
            $databaseError = app()->environment('production')
                ? 'მონაცემთა ბაზასთან კავშირი ვერ დასრულდა. გაუშვით migration და seed ბრძანებები.'
                : $e->getMessage();

            $reservations = collect();
            $guests = collect();
            $menu = collect();
            $todayReservations = collect();
            $todayCount = 0;
            $todayGuestCount = 0;
            $restaurantCapacity = 0;
            $occupancyPercent = 0;
            $todayPreorderRevenue = 0;
            $statusBreakdown = collect();
            $todayByPeriod = [
                'lunch' => ['reservations' => 0, 'guests' => 0],
                'early' => ['reservations' => 0, 'guests' => 0],
                'dinner' => ['reservations' => 0, 'guests' => 0],
            ];
        }

        return view('admin.dashboard', [
            'reservations' => $reservations,
            'status' => $status,
            'allDates' => $allDates,
            'mapStart' => $mapStart,
            'freeSeats' => $freeSeats,
            'bookingSettings' => $bookingSettings,
            'settingsHistory' => $settingsHistory,
            'guests' => $guests,
            'menu' => $menu,
            'todayReservations' => $todayReservations,
            'todayByPeriod' => $todayByPeriod,
            'today' => $today,
            'mapDate' => $mapDate,
            'todayCount' => $todayCount,
            'todayGuestCount' => $todayGuestCount,
            'restaurantCapacity' => $restaurantCapacity,
            'occupancyPercent' => $occupancyPercent,
            'todayPreorderRevenue' => $todayPreorderRevenue,
            'repeatGuests' => $guests->where('visits', '>', 1)->count(),
            'statusBreakdown' => $statusBreakdown,
            'query' => $query,
            'date' => $date,
            'databaseReady' => $databaseReady,
            'databaseError' => $databaseError,
        ]);
    }

    public function live(Request $request): \Illuminate\Http\Response
    {
        $request->attributes->set('live', true);
        $view = $this->index($request);
        if (! $view->getData()['databaseReady']) abort(503, 'Database unavailable');
        return response($view->render())->header('Cache-Control', 'no-store, private');
    }

    public function updateStatus(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'arrived', 'completed', 'cancelled', 'no_show'])],
        ]);

        DB::transaction(function () use ($reservation, $validated) {
            app(GuestCapacity::class)->settings(true);
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $allowed = [
                'confirmed' => ['arrived', 'cancelled', 'no_show'],
                'arrived' => ['completed'],
            ];
            if ($validated['status'] !== $locked->status
                && ! in_array($validated['status'], $allowed[$locked->status] ?? [], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'სტატუსის ასეთი ცვლილება დაუშვებელია. განაახლეთ გვერდი.',
                ]);
            }
            if ($locked->status !== $validated['status']) {
                DB::table('reservation_status_history')->insert([
                    'reservation_id' => $locked->id, 'from_status' => $locked->status,
                    'to_status' => $validated['status'], 'actor' => 'admin:'.config('saklshi.admin_login'),
                    'created_at' => now(),
                ]);
                $locked->update(['status' => $validated['status']]);
            }
            if (in_array($validated['status'], ['cancelled', 'no_show', 'completed'], true)) {
                $locked->slots()->delete();
            }
        }, 3);

        return back()->with('success', 'ჯავშნის სტატუსი განახლდა.');
    }

    public function updateBookingSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'capacity' => ['required', 'integer', 'min:0', 'max:10000'],
            'max_party_size' => ['required', 'integer', 'min:1', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:240', 'multiple_of:30'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:120', 'multiple_of:30'],
            'active' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($data) {
            $capacity = app(GuestCapacity::class);
            $settings = $capacity->settings(true);
            $future = Reservation::query()->whereDate('visit_date', '>=', now('Asia/Tbilisi')->toDateString())
                ->whereIn('status', GuestCapacity::ACTIVE_STATUSES)->lockForUpdate()->get()->groupBy(fn ($r) => $r->visit_date->toDateString());
            foreach ($future as $date => $reservations) {
                if ($capacity->peak($reservations) > (int) $data['capacity']) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'capacity' => $date.'-ის არსებული ჯავშნები აღემატება ახალ ტევადობას. ჯერ მოაგვარეთ არსებული ჯავშნები.',
                    ]);
                }
            }
            $before = $settings->only(['capacity', 'max_party_size', 'duration_minutes', 'buffer_minutes', 'active']);
            $settings->update($data);
            DB::table('booking_audit_logs')->insert([
                'action' => 'booking_settings.updated', 'actor' => 'admin:'.config('saklshi.admin_login'),
                'before' => json_encode($before), 'after' => json_encode($settings->only(array_keys($before))), 'created_at' => now(),
            ]);
        }, 3);
        return redirect()->to(route('admin.dashboard').'#capacity')->with('success', 'ჯავშნის პარამეტრები განახლდა.');
    }

    public function storeMenu(Request $request): RedirectResponse
    {
        MenuItem::create($this->validateMenu($request));

        return redirect()->to(route('admin.dashboard').'#menu')->with('success', 'კერძი დაემატა.');
    }

    public function updateMenu(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $menuItem->update($this->validateMenu($request));

        return redirect()->to(route('admin.dashboard').'#menu')->with('success', 'კერძი განახლდა.');
    }

    public function toggleMenu(MenuItem $menuItem): RedirectResponse
    {
        $menuItem->update(['active' => ! $menuItem->active]);

        return redirect()->to(route('admin.dashboard').'#menu')->with('success', 'მენიუს ხილვადობა განახლდა.');
    }

    public function destroyMenu(MenuItem $menuItem): RedirectResponse
    {
        // Reservation items retain their name, quantity and price snapshots.
        $menuItem->delete();

        return redirect()->to(route('admin.dashboard').'#menu')
            ->with('success', 'კერძი წაიშალა. არსებული ჯავშნების შეკვეთები შენარჩუნებულია.');
    }

    private function validateMenu(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'name_en' => ['nullable', 'string', 'max:240'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'string', 'max:120'],
            'custom_category' => ['nullable', 'string', 'max:120'],
            'price_gel' => ['required', 'numeric', 'min:0', 'max:10000'],
            'active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => trim($validated['name']),
            'name_en' => trim($validated['name_en'] ?? ''),
            'description' => trim($validated['description'] ?? ''),
            'description_en' => trim($validated['description_en'] ?? ''),
            'category' => trim((string) ($validated['custom_category'] ?? '')) ?: trim($validated['category']),
            'price' => (int) round(((float) $validated['price_gel']) * 100),
            'active' => (bool) ($validated['active'] ?? false),
        ];
    }

}
