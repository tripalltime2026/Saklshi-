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

@push('head')
<link rel="stylesheet" href="{{ asset('css/admin-operations.css') }}">
@endpush

@section('content')
<div class="admin-app" data-live-url="{{ route('admin.live') }}">
    <aside class="admin-sidebar">
        <a class="admin-logo admin-logo-image" href="{{ route('admin.dashboard') }}">
            <img src="{{ asset('images/batumis-sakhlshi-logo.png') }}" alt="ბათუმის სახლში">
        </a>

        <nav class="side-nav">
            <button type="button" data-admin-tab="bookings"><span>▤</span> რეზერვაციები</button>
            <button type="button" data-admin-tab="capacity"><span>⌘</span> ტევადობა და ჯავშანი</button>
            <button type="button" data-admin-tab="guests"><span>♟</span> სტუმრები</button>
            <button type="button" data-admin-tab="menu"><span>♨</span> მენიუ</button>
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
                <p data-live-part="headline">დღეს გაქვთ <strong>{{ $todayCount }}</strong> აქტიური რეზერვაცია.</p>
            </div>
            <div class="admin-top-actions">
                <span class="live-status" role="status" data-live-status>ავტომატური განახლება · 2 წამი</span>
                <button type="button" class="management-primary" data-refresh-now>განახლება</button>
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

        <details class="admin-exports">
            <summary>მონაცემების ჩამოტვირთვა</summary>
            <p>ჯავშნებისა და შეკვეთების CSV ითვალისწინებს არჩეულ ფილტრებს. სრული ასლი მოიცავს ყველა თარიღს.</p>
            <div>
                <a href="{{ route('admin.export', ['type' => 'reservations'] + request()->only(['q','date','status','scope'])) }}">ჯავშნები · CSV</a>
                <a href="{{ route('admin.export', ['type' => 'orders'] + request()->only(['q','date','status','scope'])) }}">შეკვეთილი კერძები · CSV</a>
                <a href="{{ route('admin.export', ['type' => 'guests']) }}">სტუმრების ბაზა · CSV</a>
                <a href="{{ route('admin.export', ['type' => 'menu']) }}">მენიუს კატალოგი · CSV</a>
                <a href="{{ route('admin.export', ['type' => 'backup']) }}">სრული მონაცემები · JSON</a>
            </div>
        </details>
        <section class="kpi-row" data-live-part="kpis">
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
                <div><small>არჩეული დროის დატვირთულობა</small><strong>{{ $occupancyPercent }}%</strong><div class="kpi-progress"><i style="width:{{ $occupancyPercent }}%"></i></div><p>{{ $restaurantCapacity }} ადგილი</p></div>
            </article>
            <article class="kpi-card">
                <span class="kpi-icon">₾</span>
                <div><small>წინასწარი შეკვეთები</small><strong>₾ {{ number_format($todayPreorderRevenue / 100, 2) }}</strong><p>დღევანდელი მენიუს წინასწარი ჯამი</p></div>
            </article>
            <a class="new-booking-btn" href="{{ route('reservation.index') }}" target="_blank" rel="noopener">＋ ახალი რეზერვაცია</a>
        </section>

        <section class="slot-summary">
            <form method="GET" action="{{ route('admin.dashboard') }}">
                <label>რუკის თარიღი <input type="date" name="date" value="{{ $mapDate }}" required></label>
                <label>დრო <select name="start">
                    @for ($slot = 720; $slot <= 1320; $slot += 30)
                        <option value="{{ $slot }}" @selected($mapStart === $slot)>{{ sprintf('%02d:%02d', intdiv($slot, 60), $slot % 60) }}</option>
                    @endfor
                </select></label>
                <button type="submit">ჩვენება</button>
            </form>
            <strong data-free-capacity data-live-part="capacity">{{ $freeSeats }} თავისუფალი ადგილი / {{ $restaurantCapacity }}</strong>
            <span>არჩეული დროიდან 2 საათით</span>
        </section>
        <section class="admin-panel" data-admin-panel="bookings">
            <div class="dashboard-grid">
                <section class="reservations-module">
                    <div class="module-tabs">
                        <a class="{{ ! $allDates && $mapDate === $today ? 'active' : '' }}" href="{{ route('admin.dashboard', ['scope' => 'day']) }}">დღეს</a>
                        <a href="{{ route('admin.dashboard', ['date' => now('Asia/Tbilisi')->addDay()->toDateString()]) }}">ხვალ</a>
                        <a class="{{ $allDates ? 'active' : '' }}" href="{{ route('admin.dashboard', ['scope' => 'all']) }}">ყველა</a>
                    </div>

                    <form class="reservation-filters" method="GET" action="{{ route('admin.dashboard') }}">
                        <div class="filter-search"><span>⌕</span><input name="q" value="{{ $query }}" placeholder="რეზერვაციის ძიება..."></div>
                        <input type="date" name="date" value="{{ $allDates ? $date : ($date ?: $today) }}">
                        <select name="status">
                            <option value="">ყველა სტატუსი</option>
                            @foreach ($statusNames as $statusCode => $statusLabel)
                                <option value="{{ $statusCode }}" @selected($status === $statusCode)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="scope" value="{{ $allDates ? 'all' : 'day' }}">
                        <input type="hidden" name="start" value="{{ $mapStart }}">
                        <button type="submit">ფილტრი</button>
                    </form>

                    <div class="reservations-table-wrap">
                        <table class="reservations-table">
                            <thead>
                                <tr>
                                    <th>დრო</th>
                                    <th>სტუმარი</th>
                                    <th>სტუმრები</th>
                                    <th>განთავსება</th>
                                    <th>მიზეზი</th>
                                    <th>სტატუსი</th>
                                    <th>შეკვეთილი მენიუ</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-live-part="booking-rows">
                                @forelse ($reservations as $reservation)
                                    @php
                                        $time = sprintf(
                                            '%02d:%02d',
                                            intdiv((int) $reservation->start_minute, 60),
                                            ((int) $reservation->start_minute) % 60
                                        );
                                    @endphp
                                    <tr>
                                        <td><strong>{{ $reservation->visit_date->format('d.m.Y') }}</strong><small>{{ $time }}–{{ sprintf('%02d:%02d', intdiv($reservation->end_minute, 60), $reservation->end_minute % 60) }}</small><small>{{ $reservation->reference }}</small></td>
                                        <td>
                                            <div class="guest-name-cell">
                                                <span class="country-dot">{{ mb_substr($reservation->first_name, 0, 1) }}</span>
                                                <div><strong>{{ $reservation->first_name }} {{ $reservation->last_name }}</strong><small>{{ $reservation->phone }}</small></div>
                                            </div>
                                        </td>
                                        <td>{{ $reservation->guests }}</td>
                                        <td><span class="table-pill">{{ $reservation->table?->name ?? 'სტუმრების რაოდენობით' }}</span></td>
                                        <td>{{ $occasionNames[$reservation->occasion] ?? '—' }}</td>
                                        <td><span class="status-pill {{ $reservation->status }}">{{ $statusNames[$reservation->status] ?? $reservation->status }}</span></td>
                                        <td class="booking-menu-cell">
                                            @if($reservation->items->isNotEmpty())
                                                <details data-order-detail="{{ $reservation->id }}">
                                                    <summary>{{ $reservation->items->sum('quantity') }} ერთეული · {{ number_format($reservation->items->sum(fn ($line) => $line->quantity * $line->unit_price) / 100, 2) }} ₾</summary>
                                                    @foreach($reservation->items as $line)
                                                        <p><strong>{{ $line->name }}</strong><br>{{ $line->quantity }} × {{ number_format($line->unit_price / 100, 2) }} ₾ = {{ number_format($line->quantity * $line->unit_price / 100, 2) }} ₾</p>
                                                    @endforeach
                                                </details>
                                            @else
                                                <span>მენიუ არ შეუკვეთავს</span>
                                            @endif
                                        </td>
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
                                            <div class="row-detail-card" role="dialog" aria-label="ჯავშნის დეტალები" hidden><button type="button" data-close-detail aria-label="დახურვა">×</button>
                                                <strong>{{ $reservation->reference }}</strong>
                                                <h3>{{ $reservation->first_name }} {{ $reservation->last_name }}</h3>
                                                <p>{{ $reservation->phone }}</p>
                                                <details><summary>სტატუსის ისტორია</summary>
                                                    @forelse($reservation->statusHistory as $change)
                                                        <p>{{ $change->created_at->timezone('Asia/Tbilisi')->format('d.m.Y H:i') }} · {{ $statusNames[$change->to_status] ?? $change->to_status }} · {{ $change->actor }}</p>
                                                    @empty
                                                        <p>ძველი ჯავშნის ისტორია არ არის შენახული.</p>
                                                    @endforelse
                                                </details>
                                                <p>{{ $reservation->visit_date->format('d.m.Y') }} · {{ $time }}–{{ sprintf('%02d:%02d', intdiv($reservation->end_minute, 60), $reservation->end_minute % 60) }}</p>
                                                <p>{{ $reservation->guests }} სტუმარი · {{ $reservation->table?->name ?? 'სტუმრების რაოდენობით' }}</p>
                                                <p>{{ $statusNames[$reservation->status] ?? $reservation->status }} · {{ $occasionNames[$reservation->occasion] ?? '—' }}</p>
                                                <p>შექმნილია: {{ $reservation->created_at->timezone('Asia/Tbilisi')->format('d.m.Y H:i:s') }} · {{ $reservation->source ?? 'Website' }}</p>
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
                                                @if ($reservation->notes)
                                                    <p>{{ $reservation->notes }}</p>
                                                @endif
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
                    <div data-live-part="booking-pages" class="booking-pages">
                        @if($reservations instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                            <span>სულ {{ $reservations->total() }} ჯავშანი · გვერდი {{ $reservations->currentPage() }} / {{ $reservations->lastPage() }}</span>
                            @if($reservations->previousPageUrl())<a href="{{ $reservations->previousPageUrl() }}">← წინა</a>@endif
                            @if($reservations->nextPageUrl())<a href="{{ $reservations->nextPageUrl() }}">შემდეგი →</a>@endif
                        @endif
                    </div>
                </section>

                <aside class="operations-column">
                    <section class="floor-card" data-live-part="floor">
                        <h3>სტუმრების ტევადობა</h3>
                        <p>{{ $mapDate }} · {{ sprintf('%02d:%02d', intdiv($mapStart, 60), $mapStart % 60) }}</p>
                        <p><strong>{{ $freeSeats }}</strong> თავისუფალი ადგილი</p>
                        <p>საერთო ტევადობა: {{ $restaurantCapacity }}</p>
                        <p>დაჯავშნა სტუმრების რაოდენობით ხდება. განთავსებას რესტორანი ადგილზე უზრუნველყოფს.</p>
                        <a href="#capacity" data-admin-tab="capacity">ტევადობის მართვა →</a>
                    </section>
                </aside>

                <aside class="insights-column" data-live-part="insights">
                    <section class="calendar-mini-card">
                        <div class="insight-head"><strong>{{ $todayCarbon->translatedFormat('F Y') }}</strong></div>
                        <div class="mini-weekdays"><span>ორშ</span><span>სამ</span><span>ოთხ</span><span>ხუთ</span><span>პარ</span><span>შაბ</span><span>კვი</span></div>
                        <div class="mini-calendar-grid">
                            @for($blank = 1; $blank < $startDow; $blank++)<span></span>@endfor
                            @for($day = 1; $day <= $daysInMonth; $day++)
                                <a class="{{ $day === (int)$todayCarbon->day ? 'today' : '' }}" href="{{ route('admin.dashboard', ['date' => $monthStart->copy()->day($day)->toDateString(), 'start' => $mapStart]) }}">{{ $day }}</a>
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
            <div class="secondary-panel-head"><div><h2>სტუმრების ბაზა</h2><p>CRM მონაცემები და განმეორებითი ვიზიტები</p></div><span data-live-part="guest-count">{{ $guests->count() }} სტუმარი</span></div>
            <div class="cards-grid" data-live-part="guests">
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
            @php
                $menuCategories = collect(config('menu.categories'))->merge($menu->pluck('category'))->unique()->values();
                $menuGroups = $menu->groupBy('category')->sortBy(fn ($items, $category) => array_flip(config('menu.categories'))[$category] ?? 999);
            @endphp
            <div class="secondary-panel-head"><div><h2>მენიუს მართვა</h2><p>კერძების დამატება, რედაქტირება და წაშლა კატეგორიების მიხედვით</p></div><span>{{ $menu->count() }} კერძი</span></div>
            <div class="management-layout">
                <form class="management-card management-form" method="POST" action="{{ route('admin.menu.store') }}">
                    @csrf
                    <h3>ახალი კერძი</h3>
                    <label>სახელი<input required maxlength="160" name="name" value="{{ old('name') }}" placeholder="კერძის სახელი"></label>
                    <label>ინგლისური სახელი<input maxlength="240" name="name_en" value="{{ old('name_en') }}"></label>
                    <label>აღწერა<textarea maxlength="2000" name="description">{{ old('description') }}</textarea></label>
                    <label>აღწერა ინგლისურად<textarea maxlength="2000" name="description_en">{{ old('description_en') }}</textarea></label>
                    <label>კატეგორია<select required name="category">
                        @foreach($menuCategories as $categoryName)
                            <option value="{{ $categoryName }}" @selected(old('category', 'ცივი კერძები') === $categoryName)>{{ $categoryName }}</option>
                        @endforeach
                    </select></label>
                    <label>ან ახალი კატეგორია<input maxlength="120" name="custom_category" value="{{ old('custom_category') }}" placeholder="არასავალდებულო"></label>
                    <label>ფასი (₾)<input required type="number" min="0" max="10000" step="0.01" name="price_gel" value="{{ old('price_gel') }}"></label>
                    <input type="hidden" name="active" value="0">
                    <label class="check-line"><input type="checkbox" name="active" value="1" @checked(old('active', 1))> გამოჩნდეს სტუმრის მენიუში</label>
                    <button class="management-primary" type="submit">კერძის დამატება</button>
                </form>
                <div data-menu-browser>
                    <div class="menu-browser-controls">
                        <label>კატეგორია<select data-menu-category aria-label="ადმინის მენიუს კატეგორია">
                            @foreach($menuGroups as $categoryName => $categoryItems)
                                <option value="{{ $categoryName }}">{{ $categoryName }} ({{ $categoryItems->count() }})</option>
                            @endforeach
                        </select></label>
                        <label>ძიება<input type="search" data-menu-search placeholder="სახელი ან აღწერა" aria-label="ადმინის მენიუში ძიება"></label>
                    </div>
                    <p class="menu-result-count" data-menu-result role="status"></p>
                    @forelse($menuGroups as $categoryName => $categoryItems)
                        <section aria-label="{{ $categoryName }}" data-menu-group="{{ $categoryName }}" @if(!$loop->first) hidden @endif>
                            <div class="secondary-panel-head"><h3>{{ $categoryName }}</h3><span>{{ $categoryItems->count() }} კერძი</span></div>
                            <div class="cards-grid">
                                @foreach($categoryItems as $item)
                                    <details class="management-card menu-edit-card" data-menu-entry data-category="{{ $categoryName }}" data-search="{{ $item->name }} {{ $item->name_en }} {{ $item->description }} {{ $item->description_en }}">
                                        <summary><span>{{ $item->name }}<small>{{ $item->name_en }}</small><small>{{ $item->active ? 'აქტიური' : 'დამალული' }}</small></span><strong>{{ number_format($item->price / 100, 2) }} ₾</strong></summary>
                                        <p>{{ $item->active ? '● აქტიური' : '○ დამალული' }}</p>
                                        <form method="POST" action="{{ route('admin.menu.update', $item) }}">
                                            @csrf @method('PUT')
                                            <label>სახელი<input required maxlength="160" name="name" value="{{ $item->name }}"></label>
                                            <label>ინგლისური სახელი<input maxlength="240" name="name_en" value="{{ $item->name_en }}"></label>
                                            <label>აღწერა<textarea maxlength="2000" name="description">{{ $item->description }}</textarea></label>
                                            <label>აღწერა ინგლისურად<textarea maxlength="2000" name="description_en">{{ $item->description_en }}</textarea></label>
                                            <label>კატეგორია<select required name="category">
                                                @foreach($menuCategories as $option)
                                                    <option value="{{ $option }}" @selected($item->category === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select></label>
                                            <label>ან ახალი კატეგორია<input maxlength="120" name="custom_category" placeholder="არასავალდებულო"></label>
                                            <label>ფასი (₾)<input required type="number" min="0" max="10000" step="0.01" name="price_gel" value="{{ number_format($item->price / 100, 2, '.', '') }}"></label>
                                            <input type="hidden" name="active" value="{{ $item->active ? 1 : 0 }}">
                                            <button type="submit">შენახვა</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.menu.toggle', $item) }}">
                                            @csrf @method('PATCH')
                                            <button class="muted-action" type="submit">{{ $item->active ? 'დროებით დამალვა' : 'გამოჩენა' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.menu.destroy', $item) }}" onsubmit="return confirm('ნამდვილად გსურთ კერძის წაშლა? არსებული ჯავშნების შეკვეთები შენარჩუნდება.');">
                                            @csrf @method('DELETE')
                                            <button class="danger-link" type="submit">კერძის წაშლა</button>
                                        </form>
                                    </details>
                                @endforeach
                            </div>
                        </section>
                    @empty
                        <div class="management-card">მენიუ ცარიელია. დაამატეთ პირველი კერძი.</div>
                    @endforelse
                    <div class="menu-empty-state" data-menu-no-results hidden>პოზიცია ვერ მოიძებნა.</div>
                    <div class="menu-pagination" data-menu-pagination hidden>
                        <button type="button" data-menu-prev>← წინა</button><span data-menu-page></span><button type="button" data-menu-next>შემდეგი →</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-panel" data-admin-panel="capacity" hidden>
            <div class="secondary-panel-head"><div><h2>ტევადობა და ჯავშნის პარამეტრები</h2><p>ჯავშნები ითვლება სტუმრების რაოდენობით.</p></div></div>
            @if($bookingSettings)
            <form class="management-card management-form" method="POST" action="{{ route('admin.booking-settings.update') }}">
                @csrf @method('PUT')
                <label>ერთდროულად მისაღები სტუმრები<input required type="number" min="0" max="10000" name="capacity" value="{{ old('capacity', $bookingSettings->capacity) }}"></label>
                <label>სტუმრების მაქსიმუმი ერთ ჯავშანში<input required type="number" min="1" max="255" name="max_party_size" value="{{ old('max_party_size', $bookingSettings->max_party_size) }}"></label>
                <label>ვიზიტის ხანგრძლივობა (წუთი)<input required type="number" min="30" max="240" step="30" name="duration_minutes" value="{{ old('duration_minutes', $bookingSettings->duration_minutes) }}"></label>
                <label>ვიზიტებს შორის შუალედი (წუთი)<input required type="number" min="0" max="120" step="30" name="buffer_minutes" value="{{ old('buffer_minutes', $bookingSettings->buffer_minutes) }}"></label>
                <input type="hidden" name="active" value="0">
                <label class="check-line"><input type="checkbox" name="active" value="1" @checked(old('active', $bookingSettings->active))> ონლაინ ჯავშნების მიღება</label>
                <p>ახალი ხანგრძლივობა და შუალედი ვრცელდება მხოლოდ ახალ ჯავშნებზე. მიღების გამორთვა არსებულ ჯავშნებს არ აუქმებს. ტევადობა ვერ შემცირდება დადასტურებული ჯავშნების რაოდენობაზე ქვემოთ.</p>
                <button class="management-primary" type="submit">შენახვა</button>
            </form>
            <div class="management-card">
                <h3>პარამეტრების ცვლილებების ისტორია</h3>
                @forelse($settingsHistory as $entry)
                    @php($values = json_decode($entry->after, true))
                    <p>{{ $entry->created_at }} · {{ $entry->actor }} · ტევადობა: {{ $values['capacity'] }} · ჯგუფი: {{ $values['max_party_size'] }} · ვიზიტი: {{ $values['duration_minutes'] }} წთ · შუალედი: {{ $values['buffer_minutes'] }} წთ · {{ $values['active'] ? 'მიღება ჩართულია' : 'მიღება შეჩერებულია' }}</p>
                @empty
                    <p>ცვლილებები ჯერ არ დაფიქსირებულა.</p>
                @endforelse
            </div>
            @endif
        </section>
    </main>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/admin.js') }}?v=20260920-capacity" defer></script>
<script src="{{ asset('js/menu-browser.js') }}" defer></script>
@endpush

