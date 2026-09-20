@extends('layouts.app')

@section('title', 'პროფილის რედაქტირება — ბათუმის სახლში')

@section('content')
<main class="account-shell">
    <section class="account-card">
        <a href="{{ route('account.dashboard') }}" class="account-back">← ჩემი ჯავშნები</a>
        <p class="account-eyebrow">პირადი მონაცემები</p>
        <h1>პროფილის რედაქტირება</h1>

        @if (session('success'))
            <div class="booking-alert success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="booking-alert error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('account.profile.update') }}" class="account-form">
            @csrf
            @method('PUT')
            <label>სახელი<input name="first_name" required value="{{ old('first_name', $user->profile?->first_name) }}"></label>
            <label>გვარი<input name="last_name" required value="{{ old('last_name', $user->profile?->last_name) }}"></label>
            <label>ელფოსტა<input type="email" name="email" value="{{ old('email', $user->email) }}"></label>
            <label>დაბადების თარიღი<input type="date" name="birth_date" value="{{ old('birth_date', $user->profile?->birth_date?->format('Y-m-d')) }}"></label>
            <label>ენა
                <select name="locale">
                    @foreach (['ka' => 'ქართული', 'en' => 'English', 'ru' => 'Русский'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('locale', $user->locale) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="account-check"><input type="checkbox" name="marketing_consent" value="1" @checked(old('marketing_consent', $user->profile?->marketing_consent))> მინდა მივიღო შეთავაზებები</label>
            <button class="book-chip" type="submit">შენახვა</button>
        </form>
    </section>
</main>
@endsection
