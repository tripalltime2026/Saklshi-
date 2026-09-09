@extends('layouts.app')

@section('title', 'მაგიდის დაჯავშნა — ბათუმის სახლში')
@section('body-class', 'reservation-shell')

@php
    $occasionLabels = [
        'banquet' => 'ბანკეტი',
        'birthday' => 'დაბადების დღე',
        'friends' => 'მეგობრები',
        'couple' => 'წყვილი',
    ];
    $oldItems = old('items', []);
@endphp

@section('content')
<div class="guest-page"
     data-min-date="{{ $today }}"
     data-max-date="{{ $maxDate }}"
     data-database-ready="{{ $databaseReady ? '1' : '0' }}">
    <header class="guest-nav">
        <a href="{{ route('reservation.index') }}" class="guest-brand guest-brand-logo" aria-label="ბათუმის სახლში">
            <img src="{{ asset('images/batumis-sakhlshi-logo.png') }}" alt="ბათუმის სახლში">
        </a>

        <nav class="guest-links" aria-label="მთავარი ნავიგაცია">
            <a href="#home">მთავარი</a>
            <a href="#menu">მენიუ</a>
            <a href="#about">ჩვენს შესახებ</a>
            <a href="#gallery">ფოტოგალერეა</a>
            <a href="#contact">კონტაქტი</a>
        </nav>

        <div class="guest-nav-actions">
            <a class="phone-chip" href="tel:+995555123456">
                <span>☎</span>
                <span><strong>+995 555 12 34 56</strong><small>დაგვიკავშირდით</small></span>
            </a>
            <a class="book-chip" href="#booking">▣ დაჯავშნე მაგიდა</a>
        </div>
    </header>

    <section class="guest-hero" id="home">
        <div class="hero-overlay"></div>
        <div class="hero-copy">
            <p>ქართული გემო, ქართული ხმა, ქართული სტუმართმოყვარეობა</p>
            <h1>ბათუმის სახლში</h1>
            <span>ქართული სამზარეულო · ცოცხალი ქართული მუსიკა ყოველდღე</span>
        </div>
        <div class="hero-script">Good Food<br>Good People<br>Georgian Soul</div>
    </section>

    <main class="booking-wrap" id="booking">
        <section class="booking-card">
            <div class="booking-title-row">
                <div>
                    <h2>დაჯავშნე მაგიდა</h2>
                    <p>შეარჩიე თარიღი, სტუმრების რაოდენობა და სურვილის შემთხვევაში წინასწარ აირჩიე მენიუ.</p>
                </div>
                <div class="booking-steps">
                    <span class="active"><b>1</b> თარიღი და დრო</span>
                    <span><b>2</b> სტუმრები და მიზეზი</span>
                    <span><b>3</b> მენიუ</span>
                    <span><b>4</b> ინფორმაცია</span>
                </div>
            </div>

            @if ($errors->any())
                <div class="booking-alert error">
                    <strong>გთხოვთ შეამოწმოთ:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! $databaseReady)
                <div class="booking-alert warning">
                    <strong>Preview რეჟიმი:</strong> დიზაინი აქტიურია, თუმცა ჯავშნის შესანახად საჭიროა ბაზის migration.
                </div>
            @endif

            <form method="POST" action="{{ route('reservation.store') }}" id="booking-form" class="booking-form">
                @csrf

                <div class="booking-grid">
                    <div class="date-column">
                        <label class="section-label"><span>1</span> აირჩიე თარიღი</label>
                        <input type="hidden" name="visit_date" value="{{ old('visit_date', $defaultVisitDate) }}" data-date-input>

                        <div class="calendar-card">
                            <div class="calendar-head">
                                <button type="button" data-cal-prev aria-label="წინა თვე">‹</button>
                                <strong data-cal-title></strong>
                                <button type="button" data-cal-next aria-label="შემდეგი თვე">›</button>
                            </div>
                            <div class="calendar-weekdays">
                                <span>ორშ</span><span>სამ</span><span>ოთხ</span><span>ხუთ</span><span>პარ</span><span>შაბ</span><span>კვი</span>
                            </div>
                            <div class="calendar-days" data-cal-days></div>
                        </div>
                    </div>

                    <div class="time-column">
                        <label class="section-label"><span>1</span> აირჩიე დრო</label>
                        <input type="hidden" name="visit_time" value="{{ old('visit_time', $defaultVisitTime) }}" data-time-input>
                        <div class="time-grid" data-time-grid>
                            @foreach (['12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00'] as $time)
                                <button type="button" data-time="{{ $time }}" class="{{ old('visit_time', $defaultVisitTime) === $time ? 'chosen' : '' }}">{{ $time }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="guest-column">
                        <label class="section-label">სტუმრების რაოდენობა</label>
                        <div class="guest-counter">
                            <button type="button" data-guest-minus aria-label="სტუმრის მოკლება">−</button>
                            <strong data-guest-count>{{ old('guests', 4) }}</strong>
                            <button type="button" data-guest-plus aria-label="სტუმრის დამატება">+</button>
                            <span>♟♟</span>
                        </div>
                        <input type="hidden" name="guests" value="{{ old('guests', 4) }}" data-guests-input>
                    </div>
                </div>

                <div class="occasion-block">
                    <label class="section-label"><span>2</span> შეხვედრის ტიპი</label>
                    <input type="hidden" name="occasion" value="{{ old('occasion', 'banquet') }}" data-occasion-input>

                    <div class="occasion-grid">
                        <button type="button" data-occasion="banquet" class="{{ old('occasion', 'banquet') === 'banquet' ? 'chosen' : '' }}">
                            <span>♬</span><strong>ბანკეტი</strong>
                        </button>
                        <button type="button" data-occasion="birthday" class="{{ old('occasion') === 'birthday' ? 'chosen' : '' }}">
                            <span>♛</span><strong>დაბადების დღე</strong>
                        </button>
                        <button type="button" data-occasion="friends" class="{{ old('occasion') === 'friends' ? 'chosen' : '' }}">
                            <span>♟♟</span><strong>მეგობრები</strong>
                        </button>
                        <button type="button" data-occasion="couple" class="{{ old('occasion') === 'couple' ? 'chosen' : '' }}">
                            <span>♡</span><strong>წყვილი</strong>
                        </button>
                    </div>
                </div>

                <section class="menu-preorder-block" id="menu">
                    <div class="menu-preorder-head">
                        <div>
                            <label class="section-label"><span>3</span> წინასწარ აირჩიე მენიუ</label>
                            <p>ეს ეტაპი არასავალდებულოა. სურვილის შემთხვევაში კერძები წინასწარ დაამატე ჯავშანს.</p>
                        </div>
                        <div class="menu-preorder-total">
                            <span><b data-menu-count>0</b> ერთეული</span>
                            <strong data-menu-total>0.00 ₾</strong>
                        </div>
                    </div>

                    @php($menuGroups = $menu->groupBy('category')->sortBy(fn ($items, $category) => array_flip(config('menu.categories'))[$category] ?? 999))
                    <div data-menu-browser>
                        <div class="menu-browser-controls">
                            <label>კატეგორია<select data-menu-category aria-label="მენიუს კატეგორია">
                                @foreach($menuGroups as $category => $items)
                                    <option value="{{ $category }}">{{ $category }} ({{ $items->count() }})</option>
                                @endforeach
                            </select></label>
                            <label>ძიება<input type="search" data-menu-search placeholder="მოძებნე კერძი ან სასმელი" aria-label="მენიუში ძიება"></label>
                            <button type="button" data-menu-selected aria-pressed="false">არჩეული მენიუ</button>
                        </div>
                        <p class="menu-result-count" data-menu-result role="status"></p>
                    @forelse ($menuGroups as $category => $items)
                        <div class="menu-category-card" data-menu-group="{{ $category }}" @if(!$loop->first) hidden @endif>
                            <div class="menu-category-title">
                                <h3>{{ $category }}</h3>
                                <span>{{ $items->count() }} პოზიცია</span>
                            </div>

                            <div class="menu-items-grid">
                                @foreach ($items as $item)
                                    @php($qty = (int) ($oldItems[$item->id] ?? 0))
                                    <article class="menu-select-item" data-menu-row data-menu-entry data-category="{{ $category }}" data-search="{{ $item->name }} {{ $item->name_en }} {{ $item->description }} {{ $item->description_en }}" data-price="{{ $item->price }}">
                                        <div class="menu-select-copy">
                                            <strong>{{ $item->name }}</strong>
                                            @if($item->name_en)<small>{{ $item->name_en }}</small>@endif
                                            @if($item->description)<p>{{ $item->description }}</p>@endif
                                            @if($item->description_en)<small>{{ $item->description_en }}</small>@endif
                                            <span>{{ number_format($item->price / 100, 2) }} ₾</span>
                                        </div>
                                        <div class="menu-counter">
                                            <button type="button" data-counter-minus aria-label="{{ $item->name }} შემცირება">−</button>
                                            <b data-counter-value>{{ $qty }}</b>
                                            <button type="button" data-counter-plus aria-label="{{ $item->name }} დამატება">+</button>
                                            <input type="hidden" name="items[{{ $item->id }}]" value="{{ $qty }}" data-qty-input @disabled($qty === 0)>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="menu-empty-state">
                            მენიუ მალე დაემატება. მაგიდის დაჯავშნა მენიუს არჩევის გარეშეც შეგიძლიათ.
                        </div>
                    @endforelse
                        <div class="menu-empty-state" data-menu-no-results hidden>პოზიცია ვერ მოიძებნა.</div>
                        <div class="menu-pagination" data-menu-pagination hidden>
                            <button type="button" data-menu-prev>← წინა</button>
                            <span data-menu-page></span>
                            <button type="button" data-menu-next>შემდეგი →</button>
                        </div>
                    </div>
                </section>

                <div class="personal-block">
                    <label class="section-label"><span>4</span> თქვენი ინფორმაცია</label>

                    <div class="personal-grid">
                        <label>სახელი
                            <input required minlength="2" maxlength="80" autocomplete="given-name" name="first_name" value="{{ old('first_name') }}" placeholder="მაგ. ანა">
                        </label>

                        <label>გვარი
                            <input required minlength="2" maxlength="80" autocomplete="family-name" name="last_name" value="{{ old('last_name') }}" placeholder="მაგ. ბერიძე">
                        </label>

                        <label>დაბადების თარიღი
                            <input required type="date" name="birth_date" value="{{ old('birth_date') }}" max="{{ $today }}">
                        </label>

                        <label>ტელეფონი
                            <input required type="tel" maxlength="24" autocomplete="tel" name="phone" value="{{ old('phone', '+995 ') }}" placeholder="+995 555 12 34 56">
                        </label>

                        <label class="notes-field">დამატებითი შენიშვნა <span>(არასავალდებულო)</span>
                            <textarea maxlength="500" name="notes" placeholder="მაგ. ფანჯარასთან მაგიდა, განსაკუთრებული ინფორმაცია...">{{ old('notes') }}</textarea>
                        </label>
                    </div>

                    <input type="hidden" name="marketing_consent" value="0">
                    <label class="marketing-consent">
                        <input type="checkbox" name="marketing_consent" value="1" {{ old('marketing_consent') ? 'checked' : '' }}>
                        <span>მსურს მივიღო პერსონალური შეთავაზებები და დაბადების დღის მოწვევა.</span>
                    </label>
                </div>
            </form>
        </section>

        <aside class="booking-summary">
            <div class="summary-head">
                <h3>თქვენი ჯავშანი</h3>
                <a href="#booking">✎ რედაქტირება</a>
            </div>

            <div class="summary-list">
                <div><span>▣ თარიღი</span><strong data-summary-date></strong></div>
                <div><span>◷ დრო</span><strong data-summary-time></strong></div>
                <div><span>♟ სტუმრები</span><strong><b data-summary-guests></b> ადამიანი</strong></div>
                <div><span>♬ მიზეზი</span><strong data-summary-occasion>{{ $occasionLabels[old('occasion', 'banquet')] }}</strong></div>
                <div><span>♨ მენიუ</span><strong data-summary-menu>არ არის არჩეული</strong></div>
            </div>

            <div class="summary-message">
                <img src="{{ asset('images/batumis-sakhlshi-logo.png') }}" alt="" class="summary-logo">
                <span>მოხარული ვიქნებით თქვენთან<br>შეხვედრით ბათუმის სახლში!</span>
            </div>

            <button type="submit" form="booking-form" class="summary-submit" {{ $databaseReady ? '' : 'disabled' }}>
                დაჯავშნე მაგიდა <span>→</span>
            </button>

            <p class="secure-note">▣ თქვენი მონაცემები დაცულია</p>
        </aside>
    </main>

    <section class="experience-strip" id="about">
        <article><span>◉</span><div><strong>ქართული სამზარეულო</strong><p>ტრადიციული ქართული გემო და ხარისხიანი პროდუქტი</p></div></article>
        <article><span>♫</span><div><strong>ცოცხალი ქართული მუსიკა ყოველდღე</strong><p>ქართული საღამოს ემოცია ყოველდღიურ რეჟიმში</p></div></article>
        <article><span>⌖</span><div><strong>ბათუმის გულში</strong><p>ადგილი მეგობრებისთვის, ოჯახისთვის და სტუმრებისთვის</p></div></article>
        <article><span>♟</span><div><strong>განსაკუთრებული მომენტები</strong><p>ბანკეტი, დაბადების დღე, წყვილი და მეგობრები</p></div></article>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/reservation.js') }}?v=20260909-booking" defer></script>
<script src="{{ asset('js/menu-browser.js') }}" defer></script>
@endpush

