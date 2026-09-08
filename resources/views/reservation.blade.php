@extends('layouts.app')

@section('title', 'მაგიდის დაჯავშნა — ბათუმის სახლში')

@php
    $initialStep = $errors->has('table_id') ? 1 : ($errors->any() ? 3 : 0);
    $oldItems = old('items', []);
@endphp

@section('content')
<div class="site reservation-app"
     data-initial-step="{{ $initialStep }}"
     data-availability-url="{{ route('reservation.availability') }}"
     data-database-ready="{{ $databaseReady ? '1' : '0' }}">

    <header class="top">
        <a class="brand" href="{{ route('reservation.index') }}">
            <span class="brandmark" aria-hidden="true">⌂</span>
            <span>ბათუმის სახლში<small>ქართული საღამოს მისამართი</small></span>
        </a>
        <span class="top-note">⌖ ბათუმი</span>
        <a class="admin-link" href="{{ route('admin.dashboard') }}">მართვა</a>
    </header>

    <main class="booking-layout">
        <aside class="editorial">
            <div class="editorial-pattern" aria-hidden="true"></div>
            <div class="editorial-copy">
                <span class="eyebrow">თქვენი საღამო იწყება აქ</span>
                <h1>საღამოს<br>შევხვდებით<br>სახლში.</h1>
                <p>შეარჩიეთ ადგილი თქვენი ამბებისთვის.</p>
                <span class="music">♪ ქართული სამზარეულო · ცოცხალი მუსიკა</span>
            </div>
        </aside>

        <section class="booking-main">
            <div class="stephead">
                <span class="eyebrow">მაგიდის დაჯავშნა</span>
                <span><b data-current-step>1</b> / 4</span>
            </div>

            <nav class="steps" aria-label="დაჯავშნის ეტაპები">
                @foreach (['ვიზიტი', 'მაგიდა', 'მენიუ', 'დადასტურება'] as $index => $label)
                    <button type="button" data-step-tab="{{ $index }}">
                        <span>{{ $index + 1 }}</span>{{ $label }}
                    </button>
                @endforeach
            </nav>

            @if ($errors->any())
                <div class="error" role="alert">
                    <strong>შეამოწმეთ ინფორმაცია:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! $databaseReady)
                <div class="warning" role="status">
                    <strong>Preview რეჟიმი:</strong> Laravel უკვე იტვირთება, თუმცა მონაცემთა ბაზა ჯერ არ არის მომზადებული.
                    Laravel Cloud-ში ბაზის დამატებისა და migration/seeder-ის გაშვების შემდეგ დაჯავშნა სრულად გააქტიურდება.
                </div>
            @endif

            <form method="POST" action="{{ route('reservation.store') }}" id="booking-form">
                @csrf

                <section class="booking-step" data-step="0">
                    <h2>როდის გელოდოთ?</h2>
                    <p class="muted">აირჩიეთ თქვენთვის სასურველი დღე და დრო.</p>

                    <div class="fields">
                        <label>
                            <span>▣ თარიღი</span>
                            <input type="date"
                                   name="visit_date"
                                   min="{{ $today }}"
                                   max="{{ $maxDate }}"
                                   value="{{ old('visit_date', $today) }}"
                                   required>
                        </label>
                        <label>
                            <span>◷ დრო</span>
                            <input type="time"
                                   name="visit_time"
                                   min="12:00"
                                   max="22:00"
                                   step="1800"
                                   value="{{ old('visit_time', '20:00') }}"
                                   required>
                        </label>
                    </div>

                    <label class="field-label">♙ სტუმრების რაოდენობა</label>
                    <input type="hidden" name="guests" value="{{ old('guests', 2) }}" data-guests-input>

                    <div class="guest-options" aria-label="სტუმრების რაოდენობა">
                        @for ($n = 1; $n <= 8; $n++)
                            <button type="button"
                                    data-guests="{{ $n }}"
                                    class="{{ (int) old('guests', 2) === $n ? 'chosen' : '' }}">
                                {{ $n }}
                            </button>
                        @endfor
                        <button type="button" data-guests="10" class="{{ (int) old('guests') === 10 ? 'chosen' : '' }}">10</button>
                        <button type="button" data-guests="12" class="{{ (int) old('guests') === 12 ? 'chosen' : '' }}">12</button>
                    </div>

                    <div class="note">
                        <span class="note-icon">◷</span>
                        <div>
                            <strong>თქვენი მაგიდა — 2 საათით</strong>
                            <p>ხელმისაწვდომობა მოწმდება არჩეული დროის მიხედვით და ახლდება ავტომატურად.</p>
                        </div>
                    </div>
                </section>

                <section class="booking-step" data-step="1" hidden>
                    <h2>აირჩიეთ თქვენი მაგიდა</h2>
                    <p class="muted" data-visit-summary></p>
                    <input type="hidden" name="table_id" value="{{ old('table_id') }}" data-table-input>

                    @if ($tables->isEmpty())
                        <div class="note">დარბაზის გეგმა ჯერ არ არის დამატებული.</div>
                    @else
                        <div class="floor" aria-label="დარბაზის 2D გეგმა">
                            <div class="windows">ფანჯრები</div>
                            <div class="stage">♪ სცენა</div>

                            @foreach ($tables as $table)
                                <button type="button"
                                        class="dining-table"
                                        data-table-id="{{ $table->id }}"
                                        data-capacity="{{ $table->capacity }}"
                                        style="left: {{ $table->x }}%; top: {{ $table->y }}%;"
                                        aria-label="{{ $table->name }}, {{ $table->capacity }} ადგილი">
                                    <b>{{ str_pad((string) $table->id, 2, '0', STR_PAD_LEFT) }}</b>
                                    <small>{{ $table->capacity }} ადგილი</small>
                                </button>
                            @endforeach

                            <div class="entrance">შესასვლელი ↑</div>
                        </div>

                        <div class="legend">
                            <span>● თავისუფალი</span>
                            <span class="teal">● არჩეული</span>
                            <span class="gray">● მიუწვდომელი</span>
                        </div>
                        <p class="muted availability-status" data-availability-status></p>
                    @endif
                </section>

                <section class="booking-step" data-step="2" hidden>
                    <h2>რას მიირთმევთ?</h2>
                    <p class="muted">შეარჩიეთ კერძები წინასწარ ან გამოტოვეთ ეს ეტაპი.</p>

                    @forelse ($menu->groupBy('category') as $category => $items)
                        <section class="menu-category">
                            <h3>{{ $category }}</h3>
                            @foreach ($items as $item)
                                @php($qty = (int) ($oldItems[$item->id] ?? 0))
                                <div class="menu-row" data-menu-row data-price="{{ $item->price }}">
                                    <span class="dish-icon">◌</span>
                                    <div class="menu-copy">
                                        <strong>{{ $item->name }}</strong>
                                        <p>{{ number_format($item->price / 100, 2) }} ₾</p>
                                    </div>
                                    <div class="counter">
                                        <button type="button" data-counter-minus aria-label="{{ $item->name }} შემცირება">−</button>
                                        <span data-counter-value>{{ $qty }}</span>
                                        <button type="button" data-counter-plus aria-label="{{ $item->name }} დამატება">+</button>
                                        <input type="hidden"
                                               name="items[{{ $item->id }}]"
                                               value="{{ $qty }}"
                                               data-qty-input>
                                    </div>
                                </div>
                            @endforeach
                        </section>
                    @empty
                        <div class="note">მენიუ მალე დაემატება. დაჯავშნა მენიუს გარეშეც შეგიძლიათ.</div>
                    @endforelse

                    <div class="menu-total">
                        <span><b data-menu-count>0</b> კერძი</span>
                        <strong data-menu-total>0.00 ₾</strong>
                    </div>
                    <p class="muted">გადახდა ადგილზე. ეს არის კერძების წინასწარი არჩევანი.</p>
                </section>

                <section class="booking-step" data-step="3" hidden>
                    <h2>როგორ მოგმართოთ?</h2>
                    <p class="muted">მონაცემები შეინახება თქვენი ჯავშნისა და მომსახურებისთვის.</p>

                    <div class="fields">
                        <label>
                            სახელი
                            <input required
                                   minlength="2"
                                   maxlength="80"
                                   autocomplete="given-name"
                                   name="first_name"
                                   value="{{ old('first_name') }}"
                                   placeholder="თქვენი სახელი">
                        </label>
                        <label>
                            გვარი
                            <input required
                                   minlength="2"
                                   maxlength="80"
                                   autocomplete="family-name"
                                   name="last_name"
                                   value="{{ old('last_name') }}"
                                   placeholder="თქვენი გვარი">
                        </label>
                    </div>

                    <label>
                        ტელეფონი
                        <input required
                               type="tel"
                               maxlength="24"
                               autocomplete="tel"
                               name="phone"
                               value="{{ old('phone', '+995 ') }}"
                               placeholder="+995 5XX XX XX XX">
                    </label>

                    <div class="fields">
                        <label>
                            დაბადების დღე
                            <input required type="number" min="1" max="31" name="birth_day" value="{{ old('birth_day') }}" placeholder="რიცხვი">
                        </label>
                        <label>
                            დაბადების თვე
                            <input required type="number" min="1" max="12" name="birth_month" value="{{ old('birth_month') }}" placeholder="თვე (1–12)">
                        </label>
                    </div>

                    <label>
                        დამატებითი სურვილი <span class="muted">(არასავალდებულო)</span>
                        <textarea maxlength="500" name="notes" placeholder="რა გავითვალისწინოთ თქვენი ვიზიტისთვის?">{{ old('notes') }}</textarea>
                    </label>

                    <input type="hidden" name="marketing_consent" value="0">
                    <label class="consent">
                        <input type="checkbox"
                               name="marketing_consent"
                               value="1"
                               {{ old('marketing_consent') ? 'checked' : '' }}>
                        <span>მსურს მივიღო პერსონალური შეთავაზებები და დაბადების დღის მოწვევა. თანხმობა ნებაყოფლობითია.</span>
                    </label>
                </section>
            </form>

            <div class="booking-footer">
                <div class="mini-summary" data-mini-summary hidden></div>
                <div class="actions">
                    <button type="button" class="back" data-back hidden>← უკან</button>
                    <span class="muted four-steps" data-four-steps>ოთხი მარტივი ნაბიჯი</span>
                    <button type="button" class="primary" data-next>
                        გაგრძელება <span>→</span>
                    </button>
                    <button type="submit"
                            form="booking-form"
                            class="primary"
                            data-submit
                            {{ $databaseReady ? '' : 'disabled' }}
                            hidden>
                        ჯავშნის დადასტურება <span>✓</span>
                    </button>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <span>ბათუმის სახლში</span>
        <span>ქართული გემო. ცოცხალი მუსიკა. თქვენი საღამო.</span>
    </footer>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/reservation.js') }}" defer></script>
@endpush
