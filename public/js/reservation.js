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
    }

    form.addEventListener('submit', (event) => {
        if (page.dataset.databaseReady !== '1') {
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

    renderCalendar();
    setGuests(guestsInput.value);
    updateSummary();
})();
