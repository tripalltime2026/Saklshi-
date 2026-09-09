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
            <button type="button" data-admin-tab="bookings"><span>▤</span> რეზერვაციები</button>
            <button type="button" data-admin-tab="tables"><span>⌘</span> მაგიდების რუკა</button>
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
                <p>დღეს გაქვთ <strong>{{ $todayCount }}</strong> აქტიური რეზერვაცია.</p>
            </div>
            <div class="admin-top-actions">
                <span class="live-status" role="status" data-live-status>ავტომატური განახლება · 5 წამი</span>
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
            <strong data-free-capacity>{{ $freeTables }} თავისუფალი მაგიდა · {{ $freeSeats }} ადგილი</strong>
            <span>არჩეული დროიდან 2 საათით</span>
        </section>
        <section class="admin-panel" data-admin-panel="bookings">
            <div class="dashboard-grid">
                <section class="reservations-module">
                    <div class="module-tabs">
                        <a class="{{ ! $allDates && $mapDate === $today ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">დღეს</a>
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
                                    <th>მაგიდა</th>
                                    <th>მიზეზი</th>
                                    <th>სტატუსი</th>
                                    <th>წყარო</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reservations as $reservation)
                                    @php
                                        $time = sprintf(
                                            '%02d:%02d',
                                            intdiv((int) $reservation->start_minute, 60),
                                            ((int) $reservation->start_minute) % 60
                                        );
                                    @endphp
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
                                            <div class="row-detail-card" role="dialog" aria-label="ჯავშნის დეტალები" hidden><button type="button" data-close-detail aria-label="დახურვა">×</button>
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
                </section>

                <aside class="operations-column">
                    <section class="floor-card">
                        <div class="card-toggle"><button type="button" class="active" data-floor-mode="map">სართულის გეგმა</button><button type="button" data-floor-mode="list">სია</button></div>
                        <div class="mini-floor">
                            <div class="floor-window-line"></div>
                            @foreach($tables->where('active', true) as $table)
                                <div class="floor-table {{ in_array($table->id, $reservedTableIds, true) ? 'busy' : 'free' }}"
                                     style="left:{{ $table->x }}%;top:{{ $table->y }}%;">
                                    <span>{{ $table->name }}</span><small>{{ $table->capacity }}</small>
                                </div>
                            @endforeach

                        </div>
                        <div class="floor-list" hidden>
                            @foreach($tables->where('active', true) as $table)
                                <div><strong>{{ $table->name }} · {{ $table->capacity }} ადგილი</strong><span>{{ in_array($table->id, $reservedTableIds, true) ? 'დაკავებული' : 'თავისუფალი' }}</span></div>
                            @endforeach
                        </div>
                        <div class="floor-legend">
                            <span><i class="free-dot"></i>თავისუფალი</span>
                            <span><i class="busy-dot"></i>დაკავებული</span>

                        </div>
                    </section>
                </aside>

                <aside class="insights-column">
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
<script src="{{ asset('js/menu-browser.js') }}" defer></script>
@endpush

