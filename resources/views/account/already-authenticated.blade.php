@extends('layouts.app')

@section('title', 'პროფილი — ბათუმის სახლში')

@section('content')
<main class="account-shell">
    <section class="account-card">
        <h1>თქვენ უკვე შესული ხართ</h1>
        <a class="book-chip" href="{{ route('account.dashboard') }}">ჩემი პროფილი</a>
    </section>
</main>
@endsection
