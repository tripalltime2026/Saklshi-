<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
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
                    ->when($date === '', fn ($q) => $q->whereDate('visit_date', $today))
                    ->when($query !== '', function ($q) use ($query) {
                        $q->where(function ($inner) use ($query) {
                            $inner->where('first_name', 'like', "%{$query}%")
                                ->orWhere('last_name', 'like', "%{$query}%")
                                ->orWhere('phone', 'like', "%{$query}%")
                                ->orWhere('reference', 'like', "%{$query}%");
                        });
                    })
                    ->orderBy('start_minute')
                    ->limit(500)
                    ->get();

                $guestRows = Reservation::query()
                    ->orderByDesc('created_at')
                    ->limit(1000)
                    ->get();

                $guests = $guestRows
                    ->groupBy('phone')
                    ->map(function ($rows) {
                        $latest = $rows->first();

                        return (object) [
                            'first_name' => $latest->first_name,
                            'last_name' => $latest->last_name,
                            'phone' => $latest->phone,
                            'birth_day' => $latest->birth_day,
                            'birth_month' => $latest->birth_month,
                            'birth_year' => $latest->birth_year ?? null,
                            'marketing_consent' => $latest->marketing_consent,
                            'last_visit' => $latest->visit_date,
                            'visits' => $rows->whereIn('status', ['arrived', 'completed'])->count(),
                        ];
                    })
                    ->values();

                $tables = DiningTable::query()->orderBy('id')->get();
                $menu = MenuItem::query()->orderBy('category')->orderBy('name')->get();

                $todayReservations = Reservation::query()
                    ->with('items')
                    ->whereDate('visit_date', $today)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->get();

                $todayCount = $todayReservations->count();
                $todayGuestCount = (int) $todayReservations->sum('guests');
                $restaurantCapacity = 210;
                $occupancyPercent = $restaurantCapacity > 0
                    ? min(100, (int) round(($todayGuestCount / $restaurantCapacity) * 100))
                    : 0;

                $todayPreorderRevenue = 0;
                foreach ($todayReservations as $reservation) {
                    foreach ($reservation->items as $item) {
                        $todayPreorderRevenue += ((int) $item->unit_price) * ((int) $item->quantity);
                    }
                }

                $reservedTableIds = Reservation::query()
                    ->whereDate('visit_date', $mapDate)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->pluck('dining_table_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

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
                $restaurantCapacity = 210;
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
            $restaurantCapacity = 210;
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

    public function updateStatus(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'arrived', 'completed', 'cancelled', 'no_show'])],
        ]);

        $reservation->update(['status' => $validated['status']]);

        if (in_array($validated['status'], ['cancelled', 'no_show'], true)) {
            $reservation->slots()->delete();
        }

        return back()->with('success', 'ჯავშნის სტატუსი განახლდა.');
    }

    public function storeMenu(Request $request): RedirectResponse
    {
        MenuItem::create($this->validateMenu($request));

        return back()->with('success', 'კერძი დაემატა.');
    }

    public function updateMenu(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $menuItem->update($this->validateMenu($request));

        return back()->with('success', 'კერძი განახლდა.');
    }

    public function toggleMenu(MenuItem $menuItem): RedirectResponse
    {
        $menuItem->update(['active' => ! $menuItem->active]);

        return back()->with('success', 'მენიუს ხილვადობა განახლდა.');
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
            'category' => ['required', 'string', 'max:120'],
            'price_gel' => ['required', 'numeric', 'min:0', 'max:10000'],
            'active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => trim($validated['name']),
            'category' => trim($validated['category']),
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
