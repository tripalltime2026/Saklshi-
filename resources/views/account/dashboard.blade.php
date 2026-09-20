@extends('layouts.app')

@section('title', 'ჩემი ჯავშნები — ბათუმის სახლში')

@section('content')
<main class="account-shell account-wide">
    <section class="account-card">
        <div class="account-head">
            <div>
                <p class="account-eyebrow">პირადი პროფილი</p>
                <h1>{{ $user->profile?->first_name ?: 'ჩემი ჯავშნები' }}</h1>
                <p>{{ $user->phone }}</p>
            </div>
            <div class="account-actions">
                <a href="{{ route('reservation.index') }}">ახალი ჯავშანი</a>
                <a href="{{ route('account.profile.edit') }}">პროფილის რედაქტირება</a>
                <form method="POST" action="{{ route('account.logout') }}">@csrf<button type="submit">გასვლა</button></form>
            </div>
        </div>

        <div class="account-reservations">
            @forelse ($reservations as $reservation)
                <article class="account-reservation">
                    <div>
                        <strong>{{ $reservation->branch?->name ?? 'ბათუმის სახლი' }}</strong>
                        <span>{{ $reservation->visit_date->format('d.m.Y') }} · {{ sprintf('%02d:%02d', intdiv($reservation->start_minute, 60), $reservation->start_minute % 60) }}</span>
                    </div>
                    <div>
                        <b>{{ $reservation->guests }} სტუმარი</b>
                        <span>{{ $reservation->reference }}</span>
                    </div>
                    <span class="account-status">{{ $reservation->status }}</span>
                </article>
            @empty
                <p>ჯავშნები ჯერ არ გაქვთ.</p>
            @endforelse
        </div>

        {{ $reservations->links() }}
    </section>
</main>
@endsection
