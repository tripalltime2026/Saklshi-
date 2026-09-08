@extends('layouts.app')

@section('title', 'ადმინისტრაცია — ბათუმის სახლში')
@section('body-class', 'admin-shell')

@php
    $statusNames = [
        'confirmed' => 'დადასტურებული',
        'arrived' => 'მოსულია',
        'completed' => 'დასრულებული',
        'cancelled' => 'გაუქმებული',
        'no_show' => 'არ გამოცხადდა',
    ];
    $occasionNames = [
        'banquet' => 'ბანკეტი',
        'birthday' => 'დაბადების დღე',
        'friends' => 'მეგობრები',
        'couple' => 'წყვილი',
    ];
    $todayCarbon = now('Asia/Tbilisi');
    $monthStart = $todayCarbon->copy()->startOfMonth();
    $daysInMonth = $todayCarbon->daysInMonth;
    $startDow = (int) $monthStart->isoWeekday();
@endphp

@section('content')
<div class="admin-app">
    <aside class="admin-sidebar">
        <a class="admin-logo admin-logo-image" href="{{ route('admin.dashboard') }}">
            <img src="{{ asset('images/batumis-sakhlshi-logo.png') }}" alt="ბათუმის სახლში">
        </a>

        <nav class="side-nav">
            <button class="active" type="button" data-admin-tab="bookings"><span>▣</span> დეშბორდი</button>
            <button type="button" data-admin-tab="bookings"><span>▤</span> რეზერვაციები</button>
            <button type="button" data-admin-tab="tables"><span>⌘</span> მაგიდების რუკა</button>
            <button type="button" data-admin-tab="guests"><span>♟</span> სტუმრები</button>
            <span class="side-muted"><b>◫</b> შეტყობინებები <i>3</i></span>
            <span class="side-muted"><b>➤</b> მარკეტინგი</span>
            <span class="side-muted"><b>▥</b> ანგარიშები</span>
            <button type="button" data-admin-tab="menu"><span>♨</span> მენიუ</button>
            <span class="side-muted"><b>♟</b> გუნდი</span>
            <span class="side-muted"><b>⚙</b> პარამეტრები</span>
        </nav>

        <div class="sidebar-quote">
            <span>ქართული გემო</span>
            <span>ქართული ხმა</span>
            <strong>ქართული სული</strong>
        </div>
    </aside>

    <main class="admin-workspace">
        <header class="admin-topbar">
            <div>
                <h1>კეთილი დღე!</h1>
                <p>დღეს გაქვთ <strong>{{ $todayCount }}</strong> აქტიური რეზერვაცია.</p>
            </div>
            <div class="admin-top-actions">
                <span class="weather-chip">☀ <b>24°C</b><small>ბათუმი</small></span>
                <form class="top-search" method="GET" action="{{ route('admin.dashboard') }}">
                    <span>⌕</span>
                    <input name="q" value="{{ $query }}" placeholder="სტუმრის ძიება (სახელი, ტელეფონი...)">
                </form>
                <a class="admin-guest-link" href="{{ route('reservation.index') }}" target="_blank">სტუმრის გვერდი ↗</a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="logout-btn" type="submit">გასვლა</button>
                </form>
            </div>
        </header>

        @if (session('success'))
            <div class="admin-flash">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="admin-error">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @if (! $databaseReady)
            <div class="admin-error">
                <strong>ბაზის მომზადება საჭიროა.</strong>
                <div>{{ $databaseError }}</div>
                <div style="margin-top:6px">Laravel Cloud → Commands: <code>php artisan migrate --force</code> და შემდეგ <code>php artisan db:seed --force</code></div>
            </div>
        @endif

        <section class="kpi-row">
            <article class="kpi-card">
                <span class="kpi-icon">▣</span>
                <div><small>დღის რეზერვაციები</small><strong>{{ $todayCount }}</strong><p>აქტიური ჯავშნები</p></div>
            </article>
            <article class="kpi-card">
                <span class="kpi-icon">♟</span>
                <div><small>სტუმრების რაოდენობა</small><strong>{{ $todayGuestCount }}</strong><p>დღევანდელი სტუმრები</p></div>
            </article>
            <article class="kpi-card">
                <span class="kpi-icon">▥</span>
                <div><small>დატვირთულობა</small><strong>{{ $occupancyPercent }}%</strong><div class="kpi-progress"><i style="width:{{ $occupancyPercent }}%"></i></div><p>{{ $restaurantCapacity }} ადგილი</p></div>
            </article>
            <article class="kpi-card">
                <span class="kpi-icon">₾</span>
                <div><small>წინასწარი შეკვეთები</small><strong>₾ {{ number_format($todayPreorderRevenue / 100, 2) }}</strong><p>დღევანდელი მენიუს წინასწარი ჯამი</p></div>
            </article>
            <button class="new-booking-btn" type="button" data-admin-tab="bookings">＋ ახალი რეზერვაცია</button>
        </section>

        <section class="admin-panel" data-admin-panel="bookings">
            <div class="dashboard-grid">
                <section class="reservations-module">
                    <div class="module-tabs">
                        <button class="active" type="button">დღეს</button>
                        <a href="{{ route('admin.dashboard', ['date' => now('Asia/Tbilisi')->addDay()->toDateString()]) }}">ხვალ</a>
                        <a href="{{ route('admin.dashboard') }}">ყველა</a>
                    </div>

                    <form class="reservation-filters" method="GET" action="{{ route('admin.dashboard') }}">
                        <div class="filter-search"><span>⌕</span><input name="q" value="{{ $query }}" placeholder="რეზერვაციის ძიება..."></div>
                        <input type="date" name="date" value="{{ $date ?: $today }}">
                        <select name="status" disabled><option>ყველა სტატუსი</option></select>
                        <button type="submit">ფილტრი</button>
                    </form>

                    <div class="reservations-table-wrap">
                        <table class="reservations-table">
                            <thead>
                                <tr>
                                    <th>დრო</th>
                                    <th>სტუმარი</th>
                                    <th>სტუმრები</th>
                                    <th>მაგიდა</th>
                                    <th>მიზეზი</th>
                                    <th>სტატუსი</th>
                                    <th>წყარო</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reservations as $reservation)
                                    @php($time = sprintf('%02d:%02d', intdiv($reservation->start_minute, 60), $reservation->start_minute % 60))
                                    <tr>
                                        <td><strong>{{ $time }}</strong><small>{{ $reservation->visit_date->format('d.m') }}</small></td>
                                        <td>
                                            <div class="guest-name-cell">
                                                <span class="country-dot">{{ mb_substr($reservation->first_name, 0, 1) }}</span>
                                                <div><strong>{{ $reservation->first_name }} {{ $reservation->last_name }}</strong><small>{{ $reservation->phone }}</small></div>
                                            </div>
                                        </td>
                                        <td>{{ $reservation->guests }}</td>
                                        <td><span class="table-pill">{{ $reservation->table?->name ?? '—' }}</span></td>
                                        <td>{{ $occasionNames[$reservation->occasion] ?? '—' }}</td>
                                        <td><span class="status-pill {{ $reservation->status }}">{{ $statusNames[$reservation->status] ?? $reservation->status }}</span></td>
                                        <td>{{ $reservation->source ?? 'Website' }}</td>
                                        <td class="row-actions">
                                            @if ($reservation->status === 'confirmed')
                                                <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="status" value="arrived">
                                                    <button title="მოსულია" type="submit">✓</button>
                                                </form>
                                            @elseif ($reservation->status === 'arrived')
                                                <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="status" value="completed">
                                                    <button title="დასრულებული" type="submit">✓</button>
                                                </form>
                                            @endif
                                            <button type="button" class="more-btn" title="დეტალები" data-row-detail>•••</button>
                                            <div class="row-detail-card" hidden>
                                                <strong>{{ $reservation->reference }}</strong>
                                                <p>დაბადება: {{ $reservation->birth_day }}/{{ $reservation->birth_month }}{{ $reservation->birth_year ? '/'.$reservation->birth_year : '' }}</p>
                                                @if($reservation->items->isNotEmpty())
                                                    <div class="admin-preorder-detail">
                                                        <span>წინასწარი მენიუ</span>
                                                        @foreach($reservation->items as $item)
                                                            <p>{{ $item->name }} × {{ $item->quantity }} — {{ number_format(($item->unit_price * $item->quantity) / 100, 2) }} ₾</p>
                                                        @endforeach
                                                        @php
                                                            $reservationMenuTotal = 0;
                                                            foreach ($reservation->items as $menuLine) {
                                                                $reservationMenuTotal += ((int) $menuLine->unit_price) * ((int) $menuLine->quantity);
                                                            }
                                                        @endphp
                                                        <strong>ჯამი: {{ number_format($reservationMenuTotal / 100, 2) }} ₾</strong>
                                                    </div>
                                                @endif
                                                @if($reservation->notes)<p>{{ $reservation->notes }}</p>@endif
                                                @if($reservation->status === 'confirmed')
                                                    <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <button class="danger-link" type="submit">გაუქმება</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="empty-row">შერჩეული პირობებით რეზერვაციები არ მოიძებნა.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside class="operations-column">
                    <section class="floor-card">
                        <div class="card-toggle"><button class="active">სართულის გეგმა</button><button>სია</button></div>
                        <div class="mini-floor">
                            <div class="floor-window-line"></div>
                            @foreach($tables as $table)
                                <div class="floor-table {{ in_array($table->id, $reservedTableIds, true) ? 'busy' : 'free' }}"
                                     style="left:{{ $table->x }}%;top:{{ $table->y }}%;">
                                    <span>{{ $table->name }}</span><small>{{ $table->capacity }}</small>
                                </div>
                            @endforeach
                            <div class="vip-table vip1">VIP1</div>
                            <div class="vip-table vip2">VIP2</div>
                        </div>
                        <div class="floor-legend">
                            <span><i class="free-dot"></i>თავისუფალი</span>
                            <span><i class="busy-dot"></i>დაკავებული</span>
                            <span><i class="vip-dot"></i>VIP</span>
                        </div>
                    </section>
                </aside>

                <aside class="insights-column">
                    <section class="calendar-mini-card">
                        <div class="insight-head"><strong>{{ $todayCarbon->translatedFormat('F Y') }}</strong><span>›</span></div>
                        <div class="mini-weekdays"><span>ორშ</span><span>სამ</span><span>ოთხ</span><span>ხუთ</span><span>პარ</span><span>შაბ</span><span>კვი</span></div>
                        <div class="mini-calendar-grid">
                            @for($blank = 1; $blank < $startDow; $blank++)<span></span>@endfor
                            @for($day = 1; $day <= $daysInMonth; $day++)
                                <span class="{{ $day === (int)$todayCarbon->day ? 'today' : '' }}">{{ $day }}</span>
                            @endfor
                        </div>
                    </section>

                    <section class="insight-card">
                        <h4>ამ დღის ჯავშნები</h4>
                        <div class="time-band"><span>12:00–16:00</span><strong>{{ $todayByPeriod['lunch']['guests'] }} სტუმარი</strong></div>
                        <div class="time-band"><span>16:00–19:00</span><strong>{{ $todayByPeriod['early']['guests'] }} სტუმარი</strong></div>
                        <div class="time-band"><span>19:00–23:00</span><strong>{{ $todayByPeriod['dinner']['guests'] }} სტუმარი</strong></div>
                    </section>

                    <section class="insight-card status-card">
                        <h4>სტატუსების გადანაწილება</h4>
                        <div class="status-donut">
                            <div class="donut-ring"></div>
                            <strong>{{ $todayCount }}<small>სულ</small></strong>
                        </div>
                        <ul>
                            <li><i class="confirmed-dot"></i>დადასტურებული <b>{{ $statusBreakdown['confirmed'] ?? 0 }}</b></li>
                            <li><i class="arrived-dot"></i>მოსულია <b>{{ $statusBreakdown['arrived'] ?? 0 }}</b></li>
                            <li><i class="completed-dot"></i>დასრულებული <b>{{ $statusBreakdown['completed'] ?? 0 }}</b></li>
                            <li><i class="cancelled-dot"></i>გაუქმებული <b>{{ $statusBreakdown['cancelled'] ?? 0 }}</b></li>
                        </ul>
                    </section>

                    <section class="insight-card activity-card">
                        <h4>ბოლო აქტივობა</h4>
                        @foreach($reservations->take(3) as $reservation)
                            <p><strong>{{ sprintf('%02d:%02d', intdiv($reservation->start_minute, 60), $reservation->start_minute % 60) }}</strong> {{ $reservation->first_name }} {{ $reservation->last_name }} — {{ $reservation->guests }} სტუმარი</p>
                        @endforeach
                    </section>
                </aside>
            </div>
        </section>

        <section class="admin-panel" data-admin-panel="guests" hidden>
            <div class="secondary-panel-head"><div><h2>სტუმრების ბაზა</h2><p>CRM მონაცემები და განმეორებითი ვიზიტები</p></div><span>{{ $guests->count() }} სტუმარი</span></div>
            <div class="cards-grid">
                @forelse($guests as $guest)
                    <article class="management-card">
                        <h3>{{ $guest->first_name }} {{ $guest->last_name }}</h3>
                        <p>{{ $guest->phone }}</p>
                        <p>დაბადება: {{ $guest->birth_day }}/{{ $guest->birth_month }}{{ $guest->birth_year ? '/'.$guest->birth_year : '' }}</p>
                        <div class="management-meta"><span>{{ $guest->visits }} ვიზიტი</span><span>{{ $guest->marketing_consent ? 'CRM ✓' : 'CRM —' }}</span></div>
                    </article>
                @empty
                    <div class="management-card">სტუმრების ბაზა ჯერ ცარიელია.</div>
                @endforelse
            </div>
        </section>

        <section class="admin-panel" data-admin-panel="menu" hidden>
            <div class="secondary-panel-head"><div><h2>მენიუს მართვა</h2><p>კერძები, კატეგორიები და ფასები</p></div></div>
            <div class="management-layout">
                <form class="management-card management-form" method="POST" action="{{ route('admin.menu.store') }}">
                    @csrf
                    <h3>ახალი კერძი</h3>
                    <label>სახელი<input required name="name" placeholder="კერძის სახელი"></label>
                    <label>კატეგორია<input required name="category" placeholder="მაგ. ცხელი კერძები"></label>
                    <label>ფასი (₾)<input required type="number" min="0" max="10000" step="0.01" name="price_gel"></label>
                    <input type="hidden" name="active" value="0">
                    <label class="check-line"><input type="checkbox" name="active" value="1" checked> აქტიური</label>
                    <button class="management-primary" type="submit">დამატება</button>
                </form>
                <div class="cards-grid">
                    @foreach($menu as $item)
                        <article class="management-card">
                            <form method="POST" action="{{ route('admin.menu.update', $item) }}">
                                @csrf @method('PUT')
                                <label>სახელი<input required name="name" value="{{ $item->name }}"></label>
                                <label>კატეგორია<input required name="category" value="{{ $item->category }}"></label>
                                <label>ფასი (₾)<input required type="number" step="0.01" name="price_gel" value="{{ number_format($item->price / 100, 2, '.', '') }}"></label>
                                <input type="hidden" name="active" value="{{ $item->active ? 1 : 0 }}">
                                <button type="submit">შენახვა</button>
                            </form>
                            <form method="POST" action="{{ route('admin.menu.toggle', $item) }}">
                                @csrf @method('PATCH')
                                <button class="muted-action" type="submit">{{ $item->active ? 'დამალვა' : 'გამოჩენა' }}</button>
                            </form>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="admin-panel" data-admin-panel="tables" hidden>
            <div class="secondary-panel-head"><div><h2>მაგიდების რუკა</h2><p>დარბაზის 2D მართვა და ტევადობა</p></div></div>
            <div class="management-layout">
                <form class="management-card management-form" method="POST" action="{{ route('admin.tables.store') }}">
                    @csrf
                    <h3>ახალი მაგიდა</h3>
                    <label>სახელი<input required name="name" placeholder="მაგიდა 13"></label>
                    <label>ადგილები<input required type="number" min="1" max="20" name="capacity" value="4"></label>
                    <label>X (%)<input required type="number" min="8" max="92" name="x" value="50"></label>
                    <label>Y (%)<input required type="number" min="12" max="88" name="y" value="50"></label>
                    <input type="hidden" name="active" value="0">
                    <label class="check-line"><input type="checkbox" name="active" value="1" checked> აქტიური</label>
                    <button class="management-primary" type="submit">დამატება</button>
                </form>
                <div class="cards-grid">
                    @foreach($tables as $table)
                        <article class="management-card">
                            <form method="POST" action="{{ route('admin.tables.update', $table) }}">
                                @csrf @method('PUT')
                                <label>სახელი<input required name="name" value="{{ $table->name }}"></label>
                                <label>ადგილები<input required type="number" min="1" max="20" name="capacity" value="{{ $table->capacity }}"></label>
                                <div class="two-fields">
                                    <label>X<input required type="number" min="8" max="92" name="x" value="{{ $table->x }}"></label>
                                    <label>Y<input required type="number" min="12" max="88" name="y" value="{{ $table->y }}"></label>
                                </div>
                                <input type="hidden" name="active" value="{{ $table->active ? 1 : 0 }}">
                                <button type="submit">შენახვა</button>
                            </form>
                            <form method="POST" action="{{ route('admin.tables.toggle', $table) }}">
                                @csrf @method('PATCH')
                                <button class="muted-action" type="submit">{{ $table->active ? 'დამალვა' : 'გამოჩენა' }}</button>
                            </form>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    </main>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/admin.js') }}" defer></script>
@endpush
