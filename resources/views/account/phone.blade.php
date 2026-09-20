@extends('layouts.app')

@section('title', 'შესვლა — ბათუმის სახლში')

@section('content')
<main class="account-shell">
    <section class="account-card">
        <a href="{{ route('reservation.index') }}" class="account-back">← დაბრუნება</a>
        <p class="account-eyebrow">პირადი პროფილი</p>
        <h1>შესვლა ტელეფონის ნომრით</h1>
        <p>მოგივათ ერთჯერადი 6-ნიშნა კოდი. პაროლი საჭირო არ არის.</p>

        @if ($errors->any())
            <div class="booking-alert error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('account.code.request') }}" class="account-form">
            @csrf
            <label>ტელეფონის ნომერი
                <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="+995 5XX XX XX XX" required autocomplete="tel">
            </label>
            <button type="submit" class="book-chip">კოდის მიღება</button>
        </form>
    </section>
</main>
@endsection
