@extends('layouts.app')

@section('title', 'ჯავშანი დადასტურებულია — ბათუმის სახლში')
@section('body-class', 'reservation-shell')

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
        <a href="{{ route('reservation.index') }}" class="guest-brand">
            <span class="guest-brand-mark">⌂</span>
            <span class="guest-brand-copy">
                <strong>ბათუმის სახლში</strong>
                <small>RESTAURANT · BATUMI</small>
            </span>
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
