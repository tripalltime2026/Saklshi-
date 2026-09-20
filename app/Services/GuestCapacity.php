<?php

namespace App\Services;

use App\Models\BookingSettings;
use App\Models\Reservation;
use Illuminate\Validation\ValidationException;

class GuestCapacity
{
    public const ACTIVE_STATUSES = ['confirmed', 'arrived'];

    // Every capacity-changing write locks the same branch settings row before reading reservations.
    public function settings(bool $lock = false, ?int $branchId = null): BookingSettings
    {
        $branchId ??= app(BranchContext::class)->id();

        return BookingSettings::query()
            ->where('branch_id', $branchId)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->firstOrFail();
    }

    public function remaining(string $date, int $start, int $end, BookingSettings $settings, bool $lock = false): int
    {
        $reservations = Reservation::query()
            ->where('branch_id', $settings->branch_id)
            ->whereDate('visit_date', $date)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('start_minute', '<', $end)
            ->where('capacity_end_minute', '>', $start)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        return max(0, $settings->capacity - $this->peak($reservations, $start, $end));
    }

    // Sweep interval boundaries rather than summing non-concurrent reservations.
    public function peak(iterable $reservations, int $start = 0, int $end = 2880): int
    {
        $events = [];
        foreach ($reservations as $reservation) {
            $from = max($start, $reservation->start_minute);
            $to = min($end, $reservation->capacity_end_minute);
            if ($from >= $to) continue;
            $events[$from] = ($events[$from] ?? 0) + $reservation->guests;
            $events[$to] = ($events[$to] ?? 0) - $reservation->guests;
        }
        ksort($events, SORT_NUMERIC);
        $used = $peak = 0;
        foreach ($events as $change) {
            $used += $change;
            $peak = max($peak, $used);
        }
        return $peak;
    }

    public function assertAvailable(string $date, int $start, int $guests, BookingSettings $settings): void
    {
        if (! $settings->active || $guests > $settings->max_party_size
            || $this->remaining($date, $start, $start + $settings->duration_minutes + $settings->buffer_minutes, $settings, true) < $guests) {
            throw ValidationException::withMessages([
                'visit_time' => 'არჩეულ დროს ამ რაოდენობის სტუმრებისთვის საკმარისი ადგილი არ არის. შეცვალეთ დრო ან სტუმრების რაოდენობა.',
            ]);
        }
    }
}
