@extends('layouts.app')

@section('title', 'სახლის მართვა — ბათუმის სახლში')
@section('body-class', 'admin-shell')

@php
    $statusNames = [
        'confirmed' => 'დადასტურებული',
        'arrived' => 'მოსულია',
        'completed' => 'დასრულებული',
        'cancelled' => 'გაუქმებული',
        'no_show' => 'არ გამოცხადდა',
    ];
@endphp

@section('content')
<header class="top">
    <a class="brand" href="{{ route('admin.dashboard') }}">
        <span class="brandmark">⌂</span>
        <span>სახლის მართვა<small>ბათუმის სახლში</small></span>
    </a>
    <a class="admin-link" style="margin-left:auto" href="{{ route('reservation.index') }}">დაჯავშნის გვერდი</a>
    <form method="POST" action="{{ route('admin.logout') }}" style="margin:0">
        @csrf
        <button class="admin-link" style="background:none;border:0" type="submit">გასვლა</button>
    </form>
</header>

<main class="admin-main">
    <div class="admin-headline">
        <div>
            <span class="eyebrow">LIVE OPERATIONS</span>
            <h1>სტუმრები და ჯავშნები</h1>
        </div>
        <span class="muted">{{ now('Asia/Tbilisi')->format('Y-m-d H:i') }} · ბათუმი</span>
    </div>

    @if (session('success'))
        <div class="flash">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="stats">
        <div class="stat"><span>დღევანდელი ჯავშნები</span><strong>{{ $todayCount }}</strong></div>
        <div class="stat"><span>სტუმრების ბაზა</span><strong>{{ $guests->count() }}</strong></div>
        <div class="stat"><span>განმეორებითი სტუმრები</span><strong>{{ $repeatGuests }}</strong></div>
    </div>

    <nav class="admin-tabs">
        <button type="button" class="active" data-admin-tab="bookings">ჯავშნები</button>
        <button type="button" data-admin-tab="guests">სტუმრების ბაზა</button>
        <button type="button" data-admin-tab="menu">მენიუ</button>
        <button type="button" data-admin-tab="tables">დარბაზი</button>
    </nav>

    <section class="admin-panel" data-admin-panel="bookings">
        <form method="GET" action="{{ route('admin.dashboard') }}" class="admin-card">
            <div class="fields">
                <label>
                    ძიება
                    <input name="q" value="{{ $query }}" placeholder="სახელი, გვარი, ტელეფონი ან კოდი">
                </label>
                <label>
                    თარიღი
                    <input type="date" name="date" value="{{ $date }}">
                </label>
            </div>
            <div class="actions">
                <button class="primary" type="submit">გაფილტვრა</button>
                <a class="link-button" href="{{ route('admin.dashboard') }}">გასუფთავება</a>
            </div>
        </form>

        @forelse ($reservations as $reservation)
            @php($time = sprintf('%02d:%02d', intdiv($reservation->start_minute, 60), $reservation->start_minute % 60))
            <article class="admin-card">
                <header>
                    <strong>{{ $reservation->first_name }} {{ $reservation->last_name }}</strong>
                    <span class="badge {{ $reservation->status }}">{{ $statusNames[$reservation->status] ?? $reservation->status }}</span>
                </header>
                <p>{{ $reservation->visit_date->format('Y-m-d') }} · {{ $time }} · {{ $reservation->table?->name }} · {{ $reservation->guests }} სტუმარი</p>
                <p><a href="tel:{{ $reservation->phone }}">{{ $reservation->phone }}</a> · დაბადების დღე: {{ $reservation->birth_day }}/{{ $reservation->birth_month }}</p>
                <p>კოდი: <strong>{{ $reservation->reference }}</strong></p>

                @if ($reservation->items->isNotEmpty())
                    <p>
                        მენიუ:
                        @foreach ($reservation->items as $item)
                            {{ $item->name }} × {{ $item->quantity }}{{ ! $loop->last ? ' · ' : '' }}
                        @endforeach
                        · <strong>{{ number_format($reservation->items->sum(fn ($i) => $i->unit_price * $i->quantity) / 100, 2) }} ₾</strong>
                    </p>
                @endif

                @if ($reservation->notes)
                    <p>სურვილი: {{ $reservation->notes }}</p>
                @endif

                <div class="actions">
                    @if ($reservation->status === 'confirmed')
                        @foreach (['arrived' => 'მოსულია', 'cancelled' => 'გაუქმება', 'no_show' => 'არ გამოცხადდა'] as $status => $label)
                            <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $status }}">
                                <button type="submit">{{ $label }}</button>
                            </form>
                        @endforeach
                    @elseif ($reservation->status === 'arrived')
                        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="completed">
                            <button type="submit">დასრულებული</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="admin-card admin-empty">შერჩეული პირობებით ჯავშნები არ მოიძებნა.</div>
        @endforelse
    </section>

    <section class="admin-panel" data-admin-panel="guests" hidden>
        <div class="admin-card">
            <p class="muted">ვიზიტებში ითვლება „მოსულია“ და „დასრულებული“ სტატუსები. ბაზა ჯგუფდება ტელეფონის ნომრით.</p>
        </div>

        @forelse ($guests as $guest)
            <article class="admin-card">
                <header>
                    <strong>{{ $guest->first_name }} {{ $guest->last_name }}</strong>
                    <span class="badge">{{ $guest->visits }} ვიზიტი</span>
                </header>
                <p><a href="tel:{{ $guest->phone }}">{{ $guest->phone }}</a> · დაბადების დღე: {{ $guest->birth_day }}/{{ $guest->birth_month }}</p>
                <p>ბოლო ჯავშანი: {{ optional($guest->last_visit)->format('Y-m-d') ?? $guest->last_visit }}</p>
                <p>პერსონალურ შეთავაზებებზე თანხმობა: {{ $guest->marketing_consent ? 'მიღებულია' : 'არ არის მიღებული' }}</p>
            </article>
        @empty
            <div class="admin-card admin-empty">პირველი ჯავშნის შემდეგ აქ გამოჩნდება სტუმრების ბაზა.</div>
        @endforelse
    </section>

    <section class="admin-panel" data-admin-panel="menu" hidden>
        <form class="admin-card admin-form" method="POST" action="{{ route('admin.menu.store') }}">
            @csrf
            <h3>ახალი კერძი</h3>
            <label>სახელი<input required name="name" placeholder="მაგ. აჭარული ხაჭაპური"></label>
            <div class="fields">
                <label>კატეგორია<input required name="category" placeholder="ცხელი კერძები"></label>
                <label>ფასი (₾)<input required type="number" min="0" max="10000" step="0.01" name="price_gel"></label>
            </div>
            <input type="hidden" name="active" value="0">
            <label class="consent"><input type="checkbox" name="active" value="1" checked><span>აქტიური — გამოჩნდეს დაჯავშნის გვერდზე</span></label>
            <button class="primary" type="submit">კერძის დამატება</button>
        </form>

        @foreach ($menu as $item)
            <article class="admin-card">
                <form method="POST" action="{{ route('admin.menu.update', $item) }}">
                    @csrf
                    @method('PUT')
                    <div class="fields">
                        <label>სახელი<input required name="name" value="{{ $item->name }}"></label>
                        <label>კატეგორია<input required name="category" value="{{ $item->category }}"></label>
                    </div>
                    <div class="fields">
                        <label>ფასი (₾)<input required type="number" min="0" max="10000" step="0.01" name="price_gel" value="{{ number_format($item->price / 100, 2, '.', '') }}"></label>
                        <label>
                            სტატუსი
                            <input value="{{ $item->active ? 'აქტიური' : 'დამალული' }}" disabled>
                        </label>
                    </div>
                    <input type="hidden" name="active" value="{{ $item->active ? 1 : 0 }}">
                    <button type="submit">ცვლილებების შენახვა</button>
                </form>
                <form method="POST" action="{{ route('admin.menu.toggle', $item) }}" style="margin-top:10px">
                    @csrf
                    @method('PATCH')
                    <button type="submit">{{ $item->active ? 'დამალვა' : 'გამოჩენა' }}</button>
                </form>
            </article>
        @endforeach
    </section>

    <section class="admin-panel" data-admin-panel="tables" hidden>
        <form class="admin-card admin-form" method="POST" action="{{ route('admin.tables.store') }}">
            @csrf
            <h3>ახალი მაგიდა</h3>
            <label>სახელი<input required name="name" placeholder="მაგიდა 13"></label>
            <div class="table-editor-grid">
                <label>ადგილები<input required type="number" min="1" max="20" name="capacity" value="4"></label>
                <label>X (%)<input required type="number" min="8" max="92" name="x" value="50"></label>
                <label>Y (%)<input required type="number" min="12" max="88" name="y" value="50"></label>
                <label style="display:flex;align-items:end"><span class="consent"><input type="checkbox" name="active" value="1" checked><span>აქტიური</span></span></label>
            </div>
            <input type="hidden" name="active" value="0">
            <button class="primary" type="submit">მაგიდის დამატება</button>
        </form>

        @foreach ($tables as $table)
            <article class="admin-card">
                <form method="POST" action="{{ route('admin.tables.update', $table) }}">
                    @csrf
                    @method('PUT')
                    <div class="fields">
                        <label>სახელი<input required name="name" value="{{ $table->name }}"></label>
                        <label>ადგილები<input required type="number" min="1" max="20" name="capacity" value="{{ $table->capacity }}"></label>
                    </div>
                    <div class="fields">
                        <label>X (%)<input required type="number" min="8" max="92" name="x" value="{{ $table->x }}"></label>
                        <label>Y (%)<input required type="number" min="12" max="88" name="y" value="{{ $table->y }}"></label>
                    </div>
                    <input type="hidden" name="active" value="{{ $table->active ? 1 : 0 }}">
                    <p class="muted">სტატუსი: {{ $table->active ? 'აქტიური' : 'დამალული' }}</p>
                    <button type="submit">ცვლილებების შენახვა</button>
                </form>
                <form method="POST" action="{{ route('admin.tables.toggle', $table) }}" style="margin-top:10px">
                    @csrf
                    @method('PATCH')
                    <button type="submit">{{ $table->active ? 'დამალვა' : 'გამოჩენა' }}</button>
                </form>
            </article>
        @endforeach
    </section>
</main>
@endsection

@push('scripts')
<script src="{{ asset('js/admin.js') }}" defer></script>
@endpush
