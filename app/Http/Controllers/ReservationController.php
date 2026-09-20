<?php

namespace App\Http\Controllers;

use App\Services\GuestCapacity;
use App\Models\BookingSettings;
use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\User;
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
    public function index(?Request $request = null): View
    {
        $databaseReady = true;
        $customer = null;
        $customerId = $request && $request->hasSession()
            ? (int) $request->session()->get('saklshi_customer_id', 0)
            : 0;
        if ($customerId > 0) {
            $customer = User::query()->with('profile')->whereKey($customerId)->where('active', true)->first();
        }

        try {
            $settings = app(GuestCapacity::class)->settings();
            $menu = MenuItem::query()
                ->where('branch_id', $settings->branch_id)
                ->where('active', true)
                ->orderBy('category')
                ->orderBy('sort_order')->orderBy('name')
                ->get();
        } catch (Throwable $e) {
            report($e);
            $databaseReady = false;
            $menu = collect();
            $settings = new BookingSettings(['max_party_size' => 20]);
        }

        // Default to the next bookable whole-hour slot in the restaurant's timezone.
        $current = now('Asia/Tbilisi');
        $nextVisit = $current->copy()->startOfHour()->addHour();
        if ($nextVisit->hour < 12) $nextVisit->setTime(12, 0);
        if ($nextVisit->hour > 22) $nextVisit->addDay()->setTime(12, 0);

        return view('reservation', [
            'databaseReady' => $databaseReady,
            'customer' => $customer,
            'maxPartySize' => $settings->max_party_size,
            'menu' => $menu,
            'today' => $current->toDateString(),
            'defaultVisitDate' => $nextVisit->toDateString(),
            'defaultVisitTime' => $nextVisit->format('H:i'),
            'maxDate' => now('Asia/Tbilisi')->addDays(90)->toDateString(),
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'integer', 'min:720', 'max:1320'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:255'],
        ]);

        $start = (int) $validated['start'];

        if ($start % 30 !== 0) {
            return response()->json(['message' => 'დრო უნდა იყოს 30-წუთიანი ინტერვალით.'], 422);
        }

        try {
            $capacity = app(GuestCapacity::class);
            $settings = $capacity->settings();
            $free = $capacity->remaining($validated['date'], $start, $start + $settings->duration_minutes + $settings->buffer_minutes, $settings);
            $guests = (int) ($validated['guests'] ?? 1);
            $visitAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $validated['date'].' '.sprintf('%02d:%02d', intdiv($start, 60), $start % 60), 'Asia/Tbilisi');
            $now = CarbonImmutable::now('Asia/Tbilisi');
            $bookable = $settings->active && $guests <= $settings->max_party_size
                && $free >= $guests && $visitAt->greaterThan($now) && $visitAt->lessThanOrEqualTo($now->addDays(90));

            return response()->json([
                'available' => $bookable ? 1 : 0,
                'free_seats' => $free,
                'capacity' => $settings->capacity,
                'max_party_size' => $settings->max_party_size,
                'duration_minutes' => $settings->duration_minutes,
                'buffer_minutes' => $settings->buffer_minutes,
                'booking_open' => $settings->active,
                'local_now' => $now->format('Y-m-d H:i'),
            ])->header('Cache-Control', 'no-store');
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'ბაზა ჯერ არ არის მომზადებული.',
                'available' => 0,
            ], 503);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'visit_date' => ['required', 'date_format:Y-m-d'],
            'visit_time' => ['required', 'regex:/^(1[2-9]|2[0-2]):(00|30)$/'],
            'guests' => ['required', 'integer', 'min:1', 'max:255'],
            'occasion' => ['required', Rule::in(['banquet', 'birthday', 'friends', 'couple'])],
            'first_name' => ['required', 'string', 'min:2', 'max:80'],
            'last_name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'string', 'max:24'],
            'birth_date' => ['required', 'date_format:Y-m-d'],
            'marketing_consent' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'menu_quantity' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'items' => ['nullable', 'array', 'max:300'],
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

        $customerId = (int) $request->session()->get('saklshi_customer_id', 0);
        $customer = $customerId > 0
            ? User::query()->whereKey($customerId)->where('active', true)->first()
            : null;

        $phone = $customer?->phone ?: preg_replace('/[^0-9+]/', '', $validated['phone']);

        if (! preg_match('/^\+?[0-9]{9,15}$/', (string) $phone)) {
            throw ValidationException::withMessages([
                'phone' => 'შეიყვანეთ სწორი ტელეფონის ნომერი.',
            ]);
        }

        $selectedItems = collect($validated['items'] ?? [])
            ->map(fn ($qty) => (int) $qty)
            ->filter(fn ($qty) => $qty > 0);

        if (isset($validated['menu_quantity']) && (int) $validated['menu_quantity'] !== (int) $selectedItems->sum()) {
            throw ValidationException::withMessages(['items' => 'არჩეული მენიუ სრულად ვერ გაიგზავნა. გთხოვთ გადაამოწმოთ შეკვეთა და სცადოთ ხელახლა.']);
        }

        if ($selectedItems->count() > 100) {
            throw ValidationException::withMessages(['items' => 'ერთ ჯავშანში აირჩიეთ მაქსიმუმ 100 განსხვავებული პოზიცია.']);
        }

        try {
            $reservation = DB::transaction(function () use ($validated, $phone, $start, $birthDate, $selectedItems, $customer) {
                $capacity = app(GuestCapacity::class);
                $settings = $capacity->settings(true);
                $capacity->assertAvailable($validated['visit_date'], $start, (int) $validated['guests'], $settings);

                $menuItems = MenuItem::query()
                    ->where('branch_id', $settings->branch_id)
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
                    'branch_id' => $settings->branch_id,
                    'user_id' => $customer?->id,
                    'reference' => $reference,
                    'visit_date' => $validated['visit_date'],
                    'start_minute' => $start,
                    'end_minute' => $start + $settings->duration_minutes,
                    'capacity_end_minute' => $start + $settings->duration_minutes + $settings->buffer_minutes,
                    'dining_table_id' => null,
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

                DB::table('reservation_status_history')->insert([
                    'reservation_id' => $reservation->id, 'from_status' => null,
                    'to_status' => 'confirmed', 'actor' => 'website', 'created_at' => now(),
                ]);

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

