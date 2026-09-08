(() => {
    const app = document.querySelector('.reservation-app');
    if (!app) return;

    const steps = [...app.querySelectorAll('[data-step]')];
    const tabs = [...app.querySelectorAll('[data-step-tab]')];
    const currentStepLabel = app.querySelector('[data-current-step]');
    const next = app.querySelector('[data-next]');
    const back = app.querySelector('[data-back]');
    const submit = app.querySelector('[data-submit]');
    const fourSteps = app.querySelector('[data-four-steps]');
    const miniSummary = app.querySelector('[data-mini-summary]');
    const dateInput = app.querySelector('[name="visit_date"]');
    const timeInput = app.querySelector('[name="visit_time"]');
    const guestsInput = app.querySelector('[data-guests-input]');
    const tableInput = app.querySelector('[data-table-input]');
    const guestButtons = [...app.querySelectorAll('[data-guests]')];
    const tableButtons = [...app.querySelectorAll('[data-table-id]')];
    const visitSummary = app.querySelector('[data-visit-summary]');
    const availabilityStatus = app.querySelector('[data-availability-status]');
    const dbReady = app.dataset.databaseReady === '1';
    const availabilityUrl = app.dataset.availabilityUrl;
    let step = Number(app.dataset.initialStep || 0);
    let occupied = new Set();

    const timeToMinutes = (value) => {
        const [h, m] = String(value || '').split(':').map(Number);
        return Number.isFinite(h) && Number.isFinite(m) ? h * 60 + m : NaN;
    };

    const formatMoney = (cents) => (cents / 100).toFixed(2) + ' ₾';

    const updateSummary = () => {
        const guests = Number(guestsInput?.value || 0);
        const selectedTable = tableButtons.find(button => button.dataset.tableId === tableInput?.value);
        const text = [dateInput?.value, timeInput?.value, guests ? guests + ' სტუმარი' : null, selectedTable?.getAttribute('aria-label')?.split(',')[0]]
            .filter(Boolean)
            .join(' · ');

        if (visitSummary) visitSummary.textContent = text;
        if (miniSummary) {
            miniSummary.textContent = text;
            miniSummary.hidden = step === 0;
        }
    };

    const updateTableStates = () => {
        const guests = Number(guestsInput?.value || 1);
        tableButtons.forEach(button => {
            const id = Number(button.dataset.tableId);
            const capacity = Number(button.dataset.capacity);
            const unavailable = occupied.has(id) || capacity < guests;
            button.classList.toggle('unavailable', unavailable);
            button.disabled = unavailable || !dbReady;

            if (unavailable && tableInput?.value === String(id)) {
                tableInput.value = '';
                button.classList.remove('selected');
            }
        });
        updateSummary();
    };

    const refreshAvailability = async () => {
        if (!dbReady || !dateInput?.value || !timeInput?.value || !availabilityUrl) {
            updateTableStates();
            return;
        }

        const start = timeToMinutes(timeInput.value);
        if (!Number.isFinite(start)) return;

        if (availabilityStatus) availabilityStatus.textContent = 'ხელმისაწვდომობა მოწმდება…';

        try {
            const url = new URL(availabilityUrl, window.location.origin);
            url.searchParams.set('date', dateInput.value);
            url.searchParams.set('start', String(start));
            const response = await fetch(url, {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (!response.ok) throw new Error(data.message || 'ხელმისაწვდომობა ვერ შემოწმდა.');

            occupied = new Set((data.occupied || []).map(Number));
            updateTableStates();
            if (availabilityStatus) availabilityStatus.textContent = 'ხელმისაწვდომობა განახლებულია.';
        } catch (error) {
            if (availabilityStatus) availabilityStatus.textContent = error.message;
        }
    };

    const validateStep = () => {
        if (step === 0) {
            if (!dateInput?.value || !timeInput?.value) {
                window.alert('აირჩიეთ თარიღი და დრო.');
                return false;
            }
        }

        if (step === 1 && !tableInput?.value) {
            window.alert(dbReady ? 'აირჩიეთ თავისუფალი მაგიდა.' : 'ჯერ მოამზადეთ მონაცემთა ბაზა Laravel Cloud-ში.');
            return false;
        }

        return true;
    };

    const renderStep = () => {
        step = Math.max(0, Math.min(3, step));

        steps.forEach((section, index) => {
            section.hidden = index !== step;
        });

        tabs.forEach((tab, index) => {
            tab.classList.toggle('current', index === step);
            tab.classList.toggle('complete', index < step);
            tab.disabled = index > step;
            const badge = tab.querySelector('span');
            if (badge) badge.textContent = index < step ? '✓' : String(index + 1);
        });

        if (currentStepLabel) currentStepLabel.textContent = String(step + 1);
        if (back) back.hidden = step === 0;
        if (fourSteps) fourSteps.hidden = step !== 0;
        if (next) {
            next.hidden = step === 3;
            next.firstChild.textContent = step === 2 && Number(app.querySelector('[data-menu-count]')?.textContent || 0) === 0
                ? 'მენიუს გარეშე გაგრძელება '
                : 'გაგრძელება ';
        }
        if (submit) submit.hidden = step !== 3;

        updateSummary();
        if (step === 1) refreshAvailability();
        window.scrollTo({top: 0, behavior: 'smooth'});
    };

    guestButtons.forEach(button => {
        button.addEventListener('click', () => {
            guestButtons.forEach(item => item.classList.remove('chosen'));
            button.classList.add('chosen');
            guestsInput.value = button.dataset.guests;
            tableInput.value = '';
            tableButtons.forEach(item => item.classList.remove('selected'));
            updateTableStates();
        });
    });

    tableButtons.forEach(button => {
        button.addEventListener('click', () => {
            if (button.disabled) return;
            tableButtons.forEach(item => item.classList.remove('selected'));
            button.classList.add('selected');
            tableInput.value = button.dataset.tableId;
            updateSummary();
        });

        if (tableInput?.value === button.dataset.tableId) {
            button.classList.add('selected');
        }
    });

    app.querySelectorAll('[data-menu-row]').forEach(row => {
        const input = row.querySelector('[data-qty-input]');
        const value = row.querySelector('[data-counter-value]');
        const minus = row.querySelector('[data-counter-minus]');
        const plus = row.querySelector('[data-counter-plus]');

        const setQty = (qty) => {
            const nextQty = Math.max(0, Math.min(20, Number(qty) || 0));
            input.value = String(nextQty);
            value.textContent = String(nextQty);
            updateMenuTotal();
        };

        minus?.addEventListener('click', () => setQty(Number(input.value) - 1));
        plus?.addEventListener('click', () => setQty(Number(input.value) + 1));
    });

    function updateMenuTotal() {
        let count = 0;
        let total = 0;

        app.querySelectorAll('[data-menu-row]').forEach(row => {
            const qty = Number(row.querySelector('[data-qty-input]')?.value || 0);
            count += qty;
            total += qty * Number(row.dataset.price || 0);
        });

        const countNode = app.querySelector('[data-menu-count]');
        const totalNode = app.querySelector('[data-menu-total]');
        if (countNode) countNode.textContent = String(count);
        if (totalNode) totalNode.textContent = formatMoney(total);
    }

    next?.addEventListener('click', () => {
        if (!validateStep()) return;
        step += 1;
        renderStep();
    });

    back?.addEventListener('click', () => {
        step -= 1;
        renderStep();
    });

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = Number(tab.dataset.stepTab);
            if (target <= step) {
                step = target;
                renderStep();
            }
        });
    });

    [dateInput, timeInput].forEach(input => {
        input?.addEventListener('change', () => {
            tableInput.value = '';
            tableButtons.forEach(item => item.classList.remove('selected'));
            occupied = new Set();
            updateSummary();
            refreshAvailability();
        });
    });

    updateMenuTotal();
    updateTableStates();
    renderStep();

    if (dbReady) {
        refreshAvailability();
        window.setInterval(refreshAvailability, 20000);
    }
})();
