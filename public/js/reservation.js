(() => {
    const page = document.querySelector('.guest-page');
    if (!page) return;

    const form = document.getElementById('booking-form');
    const dateInput = form.querySelector('[data-date-input]');
    const timeInput = form.querySelector('[data-time-input]');
    const guestsInput = form.querySelector('[data-guests-input]');
    const occasionInput = form.querySelector('[data-occasion-input]');

    const calTitle = document.querySelector('[data-cal-title]');
    const calDays = document.querySelector('[data-cal-days]');
    const prev = document.querySelector('[data-cal-prev]');
    const next = document.querySelector('[data-cal-next]');

    const minDate = page.dataset.minDate;
    const maxDate = page.dataset.maxDate;

    const parseDate = (value) => {
        const parts = value.split('-').map(Number);
        return new Date(parts[0], parts[1] - 1, parts[2]);
    };

    const toYmd = (date) => {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    };

    let selectedDate = parseDate(dateInput.value || minDate);
    let displayMonth = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);

    const min = parseDate(minDate);
    const max = parseDate(maxDate);

    const renderCalendar = () => {
        calTitle.textContent = new Intl.DateTimeFormat('ka-GE', {
            month: 'long',
            year: 'numeric'
        }).format(displayMonth);

        calDays.innerHTML = '';

        const first = new Date(displayMonth.getFullYear(), displayMonth.getMonth(), 1);
        const lastDay = new Date(displayMonth.getFullYear(), displayMonth.getMonth() + 1, 0).getDate();
        const mondayIndex = (first.getDay() + 6) % 7;

        for (let i = 0; i < mondayIndex; i += 1) {
            const blank = document.createElement('span');
            blank.className = 'calendar-blank';
            calDays.appendChild(blank);
        }

        for (let day = 1; day <= lastDay; day += 1) {
            const date = new Date(displayMonth.getFullYear(), displayMonth.getMonth(), day);
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = String(day);
            button.dataset.date = toYmd(date);

            const disabled = date < min || date > max;
            button.disabled = disabled;

            if (toYmd(date) === dateInput.value) button.classList.add('selected');
            if (toYmd(date) === minDate) button.classList.add('today');

            button.addEventListener('click', () => {
                selectedDate = date;
                dateInput.value = toYmd(date);
                renderCalendar();
                updateSummary();
            });

            calDays.appendChild(button);
        }

        const prevMonthEnd = new Date(displayMonth.getFullYear(), displayMonth.getMonth(), 0);
        const nextMonthStart = new Date(displayMonth.getFullYear(), displayMonth.getMonth() + 1, 1);
        prev.disabled = prevMonthEnd < new Date(min.getFullYear(), min.getMonth(), 1);
        next.disabled = nextMonthStart > new Date(max.getFullYear(), max.getMonth(), 1);
    };

    prev.addEventListener('click', () => {
        displayMonth = new Date(displayMonth.getFullYear(), displayMonth.getMonth() - 1, 1);
        renderCalendar();
    });

    next.addEventListener('click', () => {
        displayMonth = new Date(displayMonth.getFullYear(), displayMonth.getMonth() + 1, 1);
        renderCalendar();
    });

    document.querySelectorAll('[data-time]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-time]').forEach((b) => b.classList.remove('chosen'));
            button.classList.add('chosen');
            timeInput.value = button.dataset.time;
            updateSummary();
        });
    });

    const guestCount = document.querySelector('[data-guest-count]');

    const setGuests = (value) => {
        const nextValue = Math.max(1, Math.min(20, Number(value) || 1));
        guestsInput.value = String(nextValue);
        guestCount.textContent = String(nextValue);
        updateSummary();
    };

    document.querySelector('[data-guest-minus]').addEventListener('click', () => {
        setGuests(Number(guestsInput.value) - 1);
    });

    document.querySelector('[data-guest-plus]').addEventListener('click', () => {
        setGuests(Number(guestsInput.value) + 1);
    });

    const occasionLabels = {
        banquet: 'ბანკეტი',
        birthday: 'დაბადების დღე',
        friends: 'მეგობრები',
        couple: 'წყვილი'
    };

    document.querySelectorAll('[data-occasion]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-occasion]').forEach((b) => b.classList.remove('chosen'));
            button.classList.add('chosen');
            occasionInput.value = button.dataset.occasion;
            updateSummary();
        });
    });

    const menuRows = [...document.querySelectorAll('[data-menu-row]')];
    const menuCount = document.querySelector('[data-menu-count]');
    const menuTotal = document.querySelector('[data-menu-total]');
    const summaryMenu = document.querySelector('[data-summary-menu]');

    const recalcMenu = () => {
        let totalQty = 0;
        let totalCents = 0;

        menuRows.forEach((row) => {
            const input = row.querySelector('[data-qty-input]');
            const value = row.querySelector('[data-counter-value]');
            const qty = Math.max(0, Math.min(20, Number(input.value) || 0));
            input.value = String(qty);
            input.disabled = qty === 0;
            row.querySelector('[data-counter-minus]').disabled = qty === 0;
            row.querySelector('[data-counter-plus]').disabled = qty === 20;
            value.textContent = String(qty);

            if (qty > 0) row.classList.add('selected');
            else row.classList.remove('selected');

            totalQty += qty;
            totalCents += qty * Number(row.dataset.price || 0);
        });

        document.dispatchEvent(new Event('menu:changed'));
        if (menuCount) menuCount.textContent = String(totalQty);
        if (menuTotal) menuTotal.textContent = (totalCents / 100).toFixed(2) + ' ₾';

        if (summaryMenu) {
            summaryMenu.textContent = totalQty > 0
                ? totalQty + ' ერთეული · ' + (totalCents / 100).toFixed(2) + ' ₾'
                : 'არ არის არჩეული';
        }
    };

    menuRows.forEach((row) => {
        const input = row.querySelector('[data-qty-input]');
        const minus = row.querySelector('[data-counter-minus]');
        const plus = row.querySelector('[data-counter-plus]');

        minus.addEventListener('click', () => {
            input.value = String(Math.max(0, (Number(input.value) || 0) - 1));
            recalcMenu();
        });

        plus.addEventListener('click', () => {
            input.value = String(Math.min(20, (Number(input.value) || 0) + 1));
            recalcMenu();
        });
    });

    const summaryDate = document.querySelector('[data-summary-date]');
    const summaryTime = document.querySelector('[data-summary-time]');
    const summaryGuests = document.querySelector('[data-summary-guests]');
    const summaryOccasion = document.querySelector('[data-summary-occasion]');

    function updateSummary() {
        const d = parseDate(dateInput.value);

        summaryDate.textContent = new Intl.DateTimeFormat('ka-GE', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            weekday: 'short'
        }).format(d);

        summaryTime.textContent = timeInput.value;
        summaryGuests.textContent = guestsInput.value;
        summaryOccasion.textContent = occasionLabels[occasionInput.value] || '—';
        recalcMenu();
        refreshAvailability();
    }

    form.addEventListener('submit', (event) => {
        if (page.dataset.databaseReady !== '1' || availableCount === null || availableCount < 1 || submit.disabled) {
            event.preventDefault();
            return;
        }

        const firstInvalid = form.querySelector(':invalid');
        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.focus();
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    const availability = document.createElement('p');
    availability.setAttribute('role', 'status');
    availability.dataset.availability = '';
    const submit = document.querySelector('.summary-submit');
    submit.before(availability);
    let availableCount = null;
    let availabilityController;
    let availabilityKey = '';
    let lastChecked = 0;
    async function refreshAvailability() {
        if (page.dataset.databaseReady !== '1' || document.hidden) return;
        const [hour, minute] = timeInput.value.split(':').map(Number);
        const params = new URLSearchParams({date: dateInput.value, start: String(hour * 60 + minute), guests: guestsInput.value});
        const key = params.toString();
        if (key === availabilityKey && Date.now() - lastChecked < 4500) return;
        availabilityKey = key;
        lastChecked = Date.now();
        if (availabilityController) availabilityController.abort();
        const controller = new AbortController();
        availabilityController = controller;
        const timeout = setTimeout(() => controller.abort(), 10000);
        if (availableCount === null) submit.disabled = true;
        try {
            const response = await fetch('/api/availability?' + key, {cache: 'no-store', headers: {Accept: 'application/json'}, signal: controller.signal});
            if (!response.ok) throw new Error('availability');
            const data = await response.json();
            if (availabilityController !== controller) return;
            availableCount = Number(data.available);
            const when = parseDate(dateInput.value);
            when.setHours(hour, minute, 0, 0);
            // Use the server's Tbilisi time; visitors can be in another timezone.
            const past = dateInput.value + ' ' + timeInput.value <= data.local_now;
            submit.disabled = availableCount < 1 || past;
            availability.textContent = past ? 'აირჩიეთ მომავალი დრო.' : availableCount > 0
                ? 'თავისუფალია ' + availableCount + ' შესაბამისი მაგიდა · სულ ' + data.free_seats + ' თავისუფალი ადგილი'
                : 'არჩეულ დროს შესაბამისი მაგიდა აღარ არის. აირჩიეთ სხვა დრო.';
        } catch (error) {
            if (availabilityController !== controller) return;
            availableCount = null;
            submit.disabled = true;
            availability.textContent = 'თავისუფალი ადგილები ვერ შემოწმდა. კავშირს ხელახლა ვამოწმებთ.';
        } finally { clearTimeout(timeout); }
    }
    setInterval(refreshAvailability, 5000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) { lastChecked = 0; refreshAvailability(); }
    });

    renderCalendar();
    setGuests(guestsInput.value);
    recalcMenu();
    updateSummary();
})();

