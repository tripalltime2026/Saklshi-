<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\Models\BookingSlot;
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
        $freeTables = 0;
        $query = trim((string) $request->query('q', ''));
        $date = (string) $request->query('date', '');
        $today = now('Asia/Tbilisi')->toDateString();
        $mapDate = $date !== '' ? $date : $today;
        $databaseReady = true;
        $databaseError = null;

        try {
            $requiredTables = ['reservations', 'dining_tables', 'menu_items', 'reservation_items', 'booking_slots'];

            foreach ($requiredTables as $tableName) {
                if (! Schema::hasTable($tableName)) {
                    $databaseReady = false;
                    $databaseError = 'მონაცემთა ბაზის ცხრილები ჯერ არ არის შექმნილი.';
                    break;
                }
            }

            if ($databaseReady) {
                $reservations = Reservation::query()
                    ->with(['table', 'items'])
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

                $tables = DiningTable::query()->orderBy('id')->get();
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

                $reservedTableIds = BookingSlot::query()
                    ->whereDate('visit_date', $mapDate)
                    ->where('minute', '>=', $mapStart)
                    ->where('minute', '<', $mapStart + 120)
                    ->distinct()->pluck('dining_table_id')->map(fn ($id) => (int) $id)->all();
                $activeTables = $tables->where('active', true);
                $restaurantCapacity = (int) $activeTables->sum('capacity');
                $freeTableRows = $activeTables->whereNotIn('id', $reservedTableIds);
                $freeSeats = (int) $freeTableRows->sum('capacity');
                $freeTables = $freeTableRows->count();
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
                $tables = collect();
                $menu = collect();
                $todayReservations = collect();
                $todayCount = 0;
                $todayGuestCount = 0;
                $restaurantCapacity = 0;
                $occupancyPercent = 0;
                $todayPreorderRevenue = 0;
                $reservedTableIds = [];
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
            $tables = collect();
            $menu = collect();
            $todayReservations = collect();
            $todayCount = 0;
            $todayGuestCount = 0;
            $restaurantCapacity = 0;
            $occupancyPercent = 0;
            $todayPreorderRevenue = 0;
            $reservedTableIds = [];
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
            'freeTables' => $freeTables,
            'guests' => $guests,
            'menu' => $menu,
            'tables' => $tables,
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
            'reservedTableIds' => $reservedTableIds,
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
            $locked->update(['status' => $validated['status']]);
            if (in_array($validated['status'], ['cancelled', 'no_show', 'completed'], true)) {
                $locked->slots()->delete();
            }
        }, 3);

        return back()->with('success', 'ჯავშნის სტატუსი განახლდა.');
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

    public function storeTable(Request $request): RedirectResponse
    {
        DiningTable::create($this->validateTable($request));

        return back()->with('success', 'მაგიდა დაემატა.');
    }

    public function updateTable(Request $request, DiningTable $diningTable): RedirectResponse
    {
        $diningTable->update($this->validateTable($request, $diningTable));

        return back()->with('success', 'მაგიდა განახლდა.');
    }

    public function toggleTable(DiningTable $diningTable): RedirectResponse
    {
        $diningTable->update(['active' => ! $diningTable->active]);

        return back()->with('success', 'მაგიდის სტატუსი განახლდა.');
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

    private function validateTable(Request $request, ?DiningTable $table = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('dining_tables', 'name')->ignore($table?->id),
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'x' => ['required', 'integer', 'min:8', 'max:92'],
            'y' => ['required', 'integer', 'min:12', 'max:88'],
            'active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => trim($validated['name']),
            'capacity' => (int) $validated['capacity'],
            'x' => (int) $validated['x'],
            'y' => (int) $validated['y'],
            'active' => (bool) ($validated['active'] ?? false),
        ];
    }
}

