@extends('layouts.app')

@section('title', 'კოდის დადასტურება — ბათუმის სახლში')

@section('content')
<main class="account-shell">
    <section class="account-card">
        <a href="{{ route('account.login') }}" class="account-back">← ნომრის შეცვლა</a>
        <p class="account-eyebrow">SMS დადასტურება</p>
        <h1>შეიყვანეთ კოდი</h1>
        <p>6-ნიშნა კოდი გამოგზავნილია ნომერზე <strong>{{ $phone }}</strong>.</p>

        @if (session('status'))
            <div class="booking-alert success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="booking-alert error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('account.code.verify') }}" class="account-form">
            @csrf
            <label>დადასტურების კოდი
                <input type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" name="code" required autocomplete="one-time-code">
            </label>
            <button type="submit" class="book-chip">დადასტურება</button>
        </form>
    </section>
</main>
@endsection
