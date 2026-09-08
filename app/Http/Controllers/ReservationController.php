<?php

namespace App\Http\Controllers;

use App\Models\BookingSlot;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ReservationController extends Controller
{
    public function index(): View
    {
        $databaseReady = true;

        try {
            $tables = DiningTable::query()->where('active', true)->orderBy('id')->get();
            $menu = MenuItem::query()->where('active', true)->orderBy('category')->orderBy('name')->get();
        } catch (Throwable $e) {
            report($e);
            $databaseReady = false;
            $tables = collect([
                (object) ['id' => 1, 'name' => 'მაგიდა 01', 'capacity' => 2, 'x' => 18, 'y' => 24],
                (object) ['id' => 2, 'name' => 'მაგიდა 02', 'capacity' => 4, 'x' => 40, 'y' => 24],
                (object) ['id' => 3, 'name' => 'მაგიდა 03', 'capacity' => 4, 'x' => 62, 'y' => 24],
                (object) ['id' => 4, 'name' => 'მაგიდა 04', 'capacity' => 6, 'x' => 28, 'y' => 58],
                (object) ['id' => 5, 'name' => 'მაგიდა 05', 'capacity' => 6, 'x' => 56, 'y' => 58],
                (object) ['id' => 6, 'name' => 'მაგიდა 06', 'capacity' => 8, 'x' => 80, 'y' => 70],
            ]);
            $menu = collect([
                (object) ['id' => 1, 'category' => 'ცივი კერძები', 'name' => 'ფხალის ასორტი', 'price' => 2400],
                (object) ['id' => 2, 'category' => 'ცხელი კერძები', 'name' => 'ხინკალი ქალაქური', 'price' => 220],
                (object) ['id' => 3, 'category' => 'ცომეული', 'name' => 'აჭარული ხაჭაპური', 'price' => 2200],
            ]);
        }

        return view('reservation', [
            'tables' => $tables,
            'menu' => $menu,
            'databaseReady' => $databaseReady,
            'today' => now('Asia/Tbilisi')->toDateString(),
            'maxDate' => now('Asia/Tbilisi')->addDays(90)->toDateString(),
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'integer', 'min:720', 'max:1320'],
        ]);

        $start = (int) $validated['start'];

        if ($start % 30 !== 0) {
            return response()->json(['message' => 'დრო უნდა იყოს 30-წუთიანი ინტერვალით.'], 422);
        }

        try {
            $occupied = BookingSlot::query()
                ->whereDate('visit_date', $validated['date'])
                ->where('minute', '>=', $start)
                ->where('minute', '<', $start + 120)
                ->distinct()
                ->pluck('dining_table_id')
                ->map(fn ($id) => (int) $id)
                ->values();

            return response()->json(['occupied' => $occupied]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'ბაზა ჯერ არ არის მომზადებული. გაუშვით migration და seeder.',
                'occupied' => [],
            ], 503);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'visit_date' => ['required', 'date_format:Y-m-d'],
            'visit_time' => ['required', 'regex:/^(1[2-9]|2[0-2]):(00|30)$/'],
            'guests' => ['required', 'integer', 'min:1', 'max:12'],
            'table_id' => ['required', 'integer', 'min:1'],
            'first_name' => ['required', 'string', 'min:2', 'max:80'],
            'last_name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'string', 'max:24'],
            'birth_day' => ['required', 'integer', 'min:1', 'max:31'],
            'birth_month' => ['required', 'integer', 'min:1', 'max:12'],
            'marketing_consent' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array', 'max:100'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

        if (! checkdate((int) $validated['birth_month'], (int) $validated['birth_day'], 2000)) {
            throw ValidationException::withMessages([
                'birth_day' => 'დაბადების თარიღი არასწორია.',
            ]);
        }

        [$hour, $minute] = array_map('intval', explode(':', $validated['visit_time']));
        $start = ($hour * 60) + $minute;

        $visitAt = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            $validated['visit_date'].' '.$validated['visit_time'],
            'Asia/Tbilisi',
        );

        $now = CarbonImmutable::now('Asia/Tbilisi');

        if ($visitAt->lessThan($now) || $visitAt->greaterThan($now->addDays(90))) {
            throw ValidationException::withMessages([
                'visit_date' => 'აირჩიეთ მომავალი დრო შემდეგი 90 დღის ფარგლებში.',
            ]);
        }

        $phone = preg_replace('/[^0-9+]/', '', $validated['phone']);

        if (! preg_match('/^\+?[0-9]{9,15}$/', (string) $phone)) {
            throw ValidationException::withMessages([
                'phone' => 'შეიყვანეთ სწორი ტელეფონის ნომერი.',
            ]);
        }

        $selectedItems = collect($validated['items'] ?? [])
            ->map(fn ($qty) => (int) $qty)
            ->filter(fn ($qty) => $qty > 0)
            ->take(30);

        try {
            $reservation = DB::transaction(function () use ($validated, $selectedItems, $phone, $start) {
                $table = DiningTable::query()
                    ->whereKey($validated['table_id'])
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $table || $table->capacity < (int) $validated['guests']) {
                    throw ValidationException::withMessages([
                        'table_id' => 'აირჩიეთ სტუმრების რაოდენობის შესაბამისი თავისუფალი მაგიდა.',
                    ]);
                }

                $menuItems = MenuItem::query()
                    ->whereIn('id', $selectedItems->keys())
                    ->where('active', true)
                    ->get()
                    ->keyBy('id');

                if ($menuItems->count() !== $selectedItems->count()) {
                    throw ValidationException::withMessages([
                        'items' => 'მენიუ შეიცვალა. გთხოვთ განაახლოთ არჩევანი.',
                    ]);
                }

                do {
                    $reference = Str::upper(Str::random(8));
                } while (Reservation::query()->where('reference', $reference)->exists());

                $reservation = Reservation::create([
                    'reference' => $reference,
                    'visit_date' => $validated['visit_date'],
                    'start_minute' => $start,
                    'end_minute' => $start + 120,
                    'dining_table_id' => $table->id,
                    'guests' => (int) $validated['guests'],
                    'first_name' => trim($validated['first_name']),
                    'last_name' => trim($validated['last_name']),
                    'phone' => $phone,
                    'birth_day' => (int) $validated['birth_day'],
                    'birth_month' => (int) $validated['birth_month'],
                    'marketing_consent' => (bool) ($validated['marketing_consent'] ?? false),
                    'consent_at' => ! empty($validated['marketing_consent']) ? now() : null,
                    'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
                    'status' => 'confirmed',
                ]);

                foreach ($selectedItems as $itemId => $quantity) {
                    $item = $menuItems->get((int) $itemId);

                    $reservation->items()->create([
                        'menu_item_id' => $item->id,
                        'name' => $item->name,
                        'unit_price' => $item->price,
                        'quantity' => $quantity,
                    ]);
                }

                for ($slot = $start; $slot < $start + 120; $slot += 30) {
                    BookingSlot::create([
                        'reservation_id' => $reservation->id,
                        'dining_table_id' => $table->id,
                        'visit_date' => $validated['visit_date'],
                        'minute' => $slot,
                    ]);
                }

                return $reservation;
            }, 3);
        } catch (QueryException $e) {
            $isConflict = in_array((string) $e->getCode(), ['23000', '23505'], true)
                || str_contains(strtolower($e->getMessage()), 'unique_table_slot');

            if ($isConflict) {
                throw ValidationException::withMessages([
                    'table_id' => 'ეს მაგიდა ახლახან დაიჯავშნა. აირჩიეთ სხვა თავისუფალი მაგიდა.',
                ]);
            }

            throw $e;
        }

        return redirect()->route('reservation.confirmation', $reservation->reference);
    }

    public function confirmation(string $reference): View
    {
        $reservation = Reservation::query()
            ->with(['table', 'items'])
            ->where('reference', $reference)
            ->firstOrFail();

        return view('confirmation', compact('reservation'));
    }
}
