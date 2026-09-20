<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminExportController extends Controller
{
    private const STATUSES = ['confirmed' => 'დადასტურებული', 'arrived' => 'მოსულია', 'completed' => 'დასრულებული', 'cancelled' => 'გაუქმებული', 'no_show' => 'არ გამოცხადდა'];

    public function download(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['reservations', 'orders', 'guests', 'menu', 'backup'], true), 404);
        $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'scope' => ['nullable', Rule::in(['all', 'day'])],
        ]);
        $filename = 'saklshi-'.$type.'-'.now('Asia/Tbilisi')->format('Y-m-d-His');
        $headers = ['Cache-Control' => 'no-store, private, max-age=0', 'X-Content-Type-Options' => 'nosniff'];
        if ($type === 'backup') {
            return response()->streamDownload(function () {
                // Operational data only: never export sessions, credentials or environment configuration.
                echo '{"format":"saklshi-operational-data-v1","exported_at":'.json_encode(now()->toIso8601String()).',"currency":"GEL","prices_unit":"tetri","tables":{';
                $tables = ['dining_tables', 'menu_items', 'reservations', 'reservation_items', 'booking_slots', 'booking_settings', 'reservation_status_history', 'booking_audit_logs'];
                foreach ($tables as $index => $table) {
                    echo ($index ? ',' : '').json_encode($table).':[';
                    $first = true;
                    foreach (DB::table($table)->lazyById(250) as $row) {
                        echo ($first ? '' : ',').json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                        $first = false;
                    }
                    echo ']';
                }
                echo '}}';
            }, $filename.'.json', $headers + ['Content-Type' => 'application/json; charset=UTF-8']);
        }
        return response()->streamDownload(function () use ($request, $type) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $write = function (array $row) use ($out) {
                $safe = array_map(function ($value) {
                    $value = (string) ($value ?? '');
                    return preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
                }, $row);
                fputcsv($out, $safe, ',', '"', '');
            };
            if ($type === 'menu') {
                $write(['ID', 'კატეგორია', 'სახელი', 'ინგლისური სახელი', 'აღწერა', 'აღწერა ინგლისურად', 'ფასი GEL', 'აქტიურია']);
                foreach (MenuItem::query()->lazyById(250) as $item) {
                    $write([$item->id, $item->category, $item->name, $item->name_en, $item->description, $item->description_en, $this->gel($item->price), $item->active ? 'კი' : 'არა']);
                }
            } elseif ($type === 'guests') {
                $write(['სახელი', 'გვარი', 'ტელეფონი', 'დაბადების თარიღი', 'ვიზიტები', 'ჯავშნები', 'ბოლო დაჯავშნილი თარიღი', 'შეთავაზებებზე თანხმობა', 'თანხმობის დრო']);
                $latest = Reservation::query()->selectRaw('MAX(id)')->groupBy('phone');
                foreach (Reservation::query()->whereIn('id', $latest)->lazyById(250) as $guest) {
                    $history = Reservation::query()->where('phone', $guest->phone);
                    $write([$guest->first_name, $guest->last_name, $guest->phone,
                        sprintf('%02d.%02d', $guest->birth_day, $guest->birth_month).($guest->birth_year ? '.'.$guest->birth_year : ''),
                        (clone $history)->whereIn('status', ['arrived', 'completed'])->count(), $history->count(),
                        $guest->visit_date->format('Y-m-d'), $guest->marketing_consent ? 'კი' : 'არა', $guest->consent_at?->toIso8601String()]);
                }
            } else {
                $common = ['ჯავშნის კოდი', 'თარიღი', 'დაწყება', 'დასრულება', 'სახელი', 'გვარი', 'ტელეფონი', 'სტუმრები', 'ისტორიული მაგიდა', 'სტატუსი'];
                $write(array_merge($common, $type === 'orders'
                    ? ['კერძი', 'რაოდენობა', 'ერთეულის ფასი GEL', 'ჯამი GEL']
                    : ['მიზეზი', 'მენიუ', 'მენიუს რაოდენობა', 'მენიუს ჯამი GEL', 'შენიშვნა', 'შექმნილია', 'წყარო']));
                $query = Reservation::query()->with(['table', 'items']);
                if ($request->filled('date')) $query->whereDate('visit_date', $request->query('date'));
                elseif ($request->query('scope') === 'day') $query->whereDate('visit_date', now('Asia/Tbilisi')->toDateString());
                if ($request->filled('status')) $query->where('status', $request->query('status'));
                if ($search = trim((string) $request->query('q', ''))) {
                    $query->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('reference', 'like', "%{$search}%"));
                }
                foreach ($query->lazyById(250) as $booking) {
                    $base = [$booking->reference, $booking->visit_date->format('Y-m-d'), $this->time($booking->start_minute), $this->time($booking->end_minute), $booking->first_name, $booking->last_name, $booking->phone, $booking->guests, $booking->table?->name, self::STATUSES[$booking->status] ?? $booking->status];
                    if ($type === 'orders') {
                        foreach ($booking->items as $item) $write(array_merge($base, [$item->name, $item->quantity, $this->gel($item->unit_price), $this->gel($item->quantity * $item->unit_price)]));
                    } else {
                        $write(array_merge($base, [$booking->occasion,
                            $booking->items->map(fn ($i) => $i->name.' × '.$i->quantity.' = '.$this->gel($i->quantity * $i->unit_price).' GEL')->implode('; '),
                            $booking->items->sum('quantity'), $this->gel($booking->items->sum(fn ($i) => $i->quantity * $i->unit_price)),
                            $booking->notes, $booking->created_at->timezone('Asia/Tbilisi')->format('Y-m-d H:i:s'), $booking->source]));
                    }
                }
            }
            fclose($out);
        }, $filename.'.csv', $headers + ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function gel(int $value): string { return number_format($value / 100, 2, '.', ''); }
    private function time(int $value): string { return sprintf('%02d:%02d', intdiv($value, 60), $value % 60); }
}
