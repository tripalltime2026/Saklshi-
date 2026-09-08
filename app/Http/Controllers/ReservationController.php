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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ReservationController extends Controller
{
    public function index(): View
    {
        $databaseReady = true;

        try {
            DiningTable::query()->where('active', true)->count();
            $menu = MenuItem::query()
                ->where('active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get();
        } catch (Throwable $e) {
            report($e);
            $databaseReady = false;
            $menu = collect();
        }

        return view('reservation', [
            'databaseReady' => $databaseReady,
            'menu' => $menu,
            'today' => now('Asia/Tbilisi')->toDateString(),
            'maxDate' => now('Asia/Tbilisi')->addDays(90)->toDateString(),
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'integer', 'min:720', 'max:1320'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:20'],
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

            $available = DiningTable::query()
                ->where('active', true)
                ->where('capacity', '>=', (int) ($validated['guests'] ?? 1))
                ->whereNotIn('id', $occupied)
                ->count();

            return response()->json([
                'occupied' => $occupied,
                'available' => $available,
                'free_seats' => (int) DiningTable::query()->where('active', true)->whereNotIn('id', $occupied)->sum('capacity'),
                'local_now' => now('Asia/Tbilisi')->format('Y-m-d H:i'),
            ])->header('Cache-Control', 'no-store');
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'ბაზა ჯერ არ არის მომზადებული.',
                'occupied' => [],
                'available' => 0,
            ], 503);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'visit_date' => ['required', 'date_format:Y-m-d'],
            'visit_time' => ['required', 'regex:/^(1[2-9]|2[0-2]):(00|30)$/'],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
            'occasion' => ['required', Rule::in(['banquet', 'birthday', 'friends', 'couple'])],
            'first_name' => ['required', 'string', 'min:2', 'max:80'],
            'last_name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'string', 'max:24'],
            'birth_date' => ['required', 'date_format:Y-m-d'],
            'marketing_consent' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array', 'max:100'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

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

        $birthDate = CarbonImmutable::createFromFormat(
            'Y-m-d',
            $validated['birth_date'],
            'Asia/Tbilisi',
        );

        if ($birthDate->isFuture() || $birthDate->year < 1900) {
            throw ValidationException::withMessages([
                'birth_date' => 'შეიყვანეთ სწორი დაბადების თარიღი.',
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
            $reservation = DB::transaction(function () use ($validated, $phone, $start, $birthDate, $selectedItems) {
                $occupiedIds = BookingSlot::query()
                    ->whereDate('visit_date', $validated['visit_date'])
                    ->where('minute', '>=', $start)
                    ->where('minute', '<', $start + 120)
                    ->distinct()
                    ->pluck('dining_table_id');

                $table = DiningTable::query()
                    ->where('active', true)
                    ->where('capacity', '>=', (int) $validated['guests'])
                    ->whereNotIn('id', $occupiedIds)
                    ->orderBy('capacity')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $table) {
                    throw ValidationException::withMessages([
                        'visit_time' => 'არჩეულ დროს ამ რაოდენობის სტუმრებისთვის თავისუფალი მაგიდა აღარ არის. გთხოვთ აირჩიოთ სხვა დრო.',
                    ]);
                }

                $menuItems = MenuItem::query()
                    ->whereIn('id', $selectedItems->keys())
                    ->where('active', true)
                    ->get()
                    ->keyBy('id');

                if ($menuItems->count() !== $selectedItems->count()) {
                    throw ValidationException::withMessages([
                        'items' => 'მენიუ შეიცვალა. გთხოვთ განაახლოთ გვერდი და თავიდან აირჩიოთ კერძები.',
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
                    'occasion' => $validated['occasion'],
                    'first_name' => trim($validated['first_name']),
                    'last_name' => trim($validated['last_name']),
                    'phone' => $phone,
                    'birth_day' => $birthDate->day,
                    'birth_month' => $birthDate->month,
                    'birth_year' => $birthDate->year,
                    'source' => 'Website',
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
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            $isConflict = in_array((string) $e->getCode(), ['23000', '23505'], true)
                || str_contains(strtolower($e->getMessage()), 'unique_table_slot');

            if ($isConflict) {
                throw ValidationException::withMessages([
                    'visit_time' => 'არჩეული დრო ახლახან შეივსო. გთხოვთ სცადოთ სხვა დრო.',
                ]);
            }

            report($e);

            return back()
                ->withInput()
                ->withErrors(['reservation' => 'ჯავშნის შენახვა ვერ დასრულდა. გთხოვთ სცადოთ ხელახლა.']);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors(['reservation' => 'ტექნიკური შეცდომა დაფიქსირდა. მონაცემები არ დაკარგულა — გთხოვთ სცადოთ ხელახლა.']);
        }

        return redirect()->route('reservation.confirmation', ['reference' => $reservation->reference]);
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
