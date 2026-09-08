@extends('layouts.app')

@section('title', 'ჯავშანი დადასტურებულია — ბათუმის სახლში')
@section('body-class', 'success-page')

@php
    $time = sprintf('%02d:%02d', intdiv($reservation->start_minute, 60), $reservation->start_minute % 60);
    $total = $reservation->items->sum(fn ($item) => $item->unit_price * $item->quantity);
@endphp

@section('content')
<header class="top print-hide">
    <a class="brand" href="{{ route('reservation.index') }}">
        <span class="brandmark">⌂</span>
        <span>ბათუმის სახლში<small>ქართული საღამოს მისამართი</small></span>
    </a>
    <span class="top-note">⌖ ბათუმი</span>
</header>

<main class="success-wrap">
    <div class="success-icon">✓</div>
    <span class="eyebrow">გელოდებით სახლში</span>
    <h1 style="font-size:36px;margin:12px 0 8px;">მაგიდა დაჯავშნილია</h1>
    <p class="muted">{{ $reservation->first_name }}, თქვენი ჯავშანი სისტემაში შენახულია.</p>

    <div class="summarybox">
        <strong>{{ $reservation->visit_date->format('Y-m-d') }} · {{ $time }}</strong>
        <p>{{ $reservation->table?->name }} · {{ $reservation->guests }} სტუმარი · 2 საათი</p>

        @if ($reservation->items->isNotEmpty())
            <div style="margin-top:16px;">
                @foreach ($reservation->items as $item)
                    <p>{{ $item->name }} × {{ $item->quantity }} — {{ number_format(($item->unit_price * $item->quantity) / 100, 2) }} ₾</p>
                @endforeach
                <p><strong>მენიუ ჯამში: {{ number_format($total / 100, 2) }} ₾</strong></p>
            </div>
        @else
            <p>მენიუ წინასწარ არ არის არჩეული.</p>
        @endif

        @if ($reservation->notes)
            <p style="margin-top:12px;">სურვილი: {{ $reservation->notes }}</p>
        @endif

        <small>ჯავშნის კოდი: <strong>{{ $reservation->reference }}</strong></small>
    </div>

    <p class="muted">მენიუს თანხა გადაიხდება რესტორანში. შეინახეთ ჯავშნის კოდი.</p>

    <div class="print-hide" style="margin-top:24px;">
        <button class="link-button" onclick="window.print()">დადასტურების ბეჭდვა</button>
        <a class="link-button" href="{{ route('reservation.index') }}">ახალი ჯავშანი</a>
    </div>
</main>
@endsection
