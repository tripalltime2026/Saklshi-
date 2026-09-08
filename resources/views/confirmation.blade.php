@extends('layouts.app')

@section('title', 'ჯავშანი დადასტურებულია — ბათუმის სახლში')
@section('body-class', 'reservation-shell')

@push('head')
<link rel="stylesheet" href="{{ asset('css/confirmation.css') }}">
@endpush

@php
    $time = sprintf('%02d:%02d', intdiv($reservation->start_minute, 60), $reservation->start_minute % 60);
    $occasionNames = [
        'banquet' => 'ბანკეტი',
        'birthday' => 'დაბადების დღე',
        'friends' => 'მეგობრები',
        'couple' => 'წყვილი',
    ];
@endphp

@section('content')
<div class="confirmation-page">
    <header class="guest-nav confirmation-nav print-hide">
        <a href="{{ route('reservation.index') }}" class="guest-brand guest-brand-logo">
            <img src="{{ asset('images/batumis-sakhlshi-logo.png') }}" alt="ბათუმის სახლში">
        </a>
        <div></div>
        <a class="book-chip" href="{{ route('reservation.index') }}">ახალი ჯავშანი</a>
    </header>

    <main class="confirmation-hero">
        <section class="confirmation-box">
            <div class="confirmation-check">✓</div>
            <span class="confirmation-eyebrow">გელოდებით სახლში</span>
            <h1>მაგიდა დაჯავშნილია</h1>
            <p>{{ $reservation->first_name }}, თქვენი ჯავშანი წარმატებით შევინახეთ.</p>

            <div class="confirmation-summary">
                <div><span>თარიღი</span><strong>{{ $reservation->visit_date->translatedFormat('d F, Y') }}</strong></div>
                <div><span>დრო</span><strong>{{ $time }}</strong></div>
                <div><span>სტუმრები</span><strong>{{ $reservation->guests }} ადამიანი</strong></div>
                <div><span>მიზეზი</span><strong>{{ $occasionNames[$reservation->occasion] ?? 'რეზერვაცია' }}</strong></div>
                <div><span>მაგიდა</span><strong>{{ $reservation->table?->name ?? 'დადასტურებულია' }}</strong></div>
                <div><span>ჯავშნის კოდი</span><strong>{{ $reservation->reference }}</strong></div>
            </div>

            @if ($reservation->items->isNotEmpty())
                <div class="confirmation-note confirmation-menu">
                    <span>წინასწარ არჩეული მენიუ</span>
                    @foreach($reservation->items as $item)
                        <p>{{ $item->name }} × {{ $item->quantity }} — {{ number_format(($item->unit_price * $item->quantity) / 100, 2) }} ₾</p>
                    @endforeach
                    <strong>ჯამი: {{ number_format($reservation->items->sum(fn($item) => $item->unit_price * $item->quantity) / 100, 2) }} ₾</strong>
                </div>
            @endif

            @if ($reservation->notes)
                <div class="confirmation-note"><span>შენიშვნა</span><p>{{ $reservation->notes }}</p></div>
            @endif

            <div class="confirmation-welcome">⌂ <span>მოხარული ვიქნებით თქვენთან შეხვედრით<br>ბათუმის სახლში.</span></div>

            <div class="confirmation-actions print-hide">
                <button type="button" onclick="window.print()">დადასტურების ბეჭდვა</button>
                <a href="{{ route('reservation.index') }}">ახალი ჯავშანი →</a>
            </div>
        </section>
    </main>
</div>
@endsection
