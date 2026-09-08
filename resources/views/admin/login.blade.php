@extends('layouts.app')

@section('title', 'ადმინისტრაცია — ბათუმის სახლში')
@section('body-class', 'admin-shell')

@section('content')
<header class="top">
    <a class="brand guest-brand-logo" href="{{ route('reservation.index') }}">
        <img src="{{ asset('images/batumis-sakhlshi-logo.png') }}" alt="ბათუმის სახლში">
    </a>
    <a class="admin-link" style="margin-left:auto" href="{{ route('reservation.index') }}">დაჯავშნის გვერდი</a>
</header>

<main class="admin-main">
    <section class="login-card">
        <span class="eyebrow">ADMIN</span>
        <h1>სახლის მართვა</h1>
        <p class="muted">შედით ჯავშნების, სტუმრების, მენიუსა და დარბაზის სამართავად.</p>

        @if ($errors->any())
            <div class="error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.submit') }}">
            @csrf
            <label>
                ლოგინი
                <input type="text" name="login" autocomplete="username" required value="{{ old('login', 'admin') }}">
            </label>
            <label>
                პაროლი
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="primary" type="submit">შესვლა →</button>
        </form>
    </section>
</main>
@endsection
