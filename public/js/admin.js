(() => {
    let active = ['bookings', 'tables', 'guests', 'menu'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'bookings';
    let dirty = false;
    let refreshing = false;
    let floorMode = 'map';
    const activate = (name) => {
        active = name;
        document.querySelectorAll('[data-admin-tab]').forEach(el => el.classList.toggle('active', el.dataset.adminTab === name));
        document.querySelectorAll('[data-admin-panel]').forEach(el => { el.hidden = el.dataset.adminPanel !== name; });
        history.replaceState(null, '', '#' + name);
    };
    const setFloor = () => {
        document.querySelectorAll('.mini-floor').forEach(el => { el.hidden = floorMode !== 'map'; });
        document.querySelectorAll('.floor-list').forEach(el => { el.hidden = floorMode !== 'list'; });
        document.querySelectorAll('[data-floor-mode]').forEach(el => el.classList.toggle('active', el.dataset.floorMode === floorMode));
    };
    const closeDetails = () => document.querySelectorAll('.row-detail-card').forEach(el => { el.hidden = true; });
    document.addEventListener('click', event => {
        const tab = event.target.closest('[data-admin-tab]');
        if (tab) { activate(tab.dataset.adminTab); return; }
        const mode = event.target.closest('[data-floor-mode]');
        if (mode) { floorMode = mode.dataset.floorMode; setFloor(); return; }
        const detail = event.target.closest('[data-row-detail]');
        if (detail) {
            const card = detail.parentElement.querySelector('.row-detail-card');
            const wasOpen = !card.hidden;
            closeDetails();
            card.hidden = wasOpen;
            if (!wasOpen) card.querySelector('[data-close-detail]').focus();
            return;
        }
        if (event.target.closest('[data-close-detail]') || !event.target.closest('.row-detail-card')) closeDetails();
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeDetails(); });
    document.addEventListener('input', event => {
        if (event.target.closest('.reservation-filters, .slot-summary form, .top-search')) dirty = true;
    });
    activate(active);
    const refresh = async () => {
        if (document.hidden || refreshing) return;
        refreshing = true;
        const indicator = document.querySelector('[data-live-status]');
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(location.pathname + location.search, {
                cache: 'no-store', headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json'}, signal: controller.signal
            });
            if (response.status === 401 || (response.redirected && new URL(response.url).pathname.endsWith('/admin/login'))) {
                location.assign('/admin/login'); return;
            }
            if (!response.ok) throw new Error('refresh');
            const incoming = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (!incoming.querySelector('.admin-app')) throw new Error('invalid page');
            // Never replace a form that is being edited or an open reservation.
            const busy = dirty || document.activeElement?.matches('input, select, textarea') || document.querySelector('.row-detail-card:not([hidden])');
            const selectors = ['.kpi-row', '[data-free-capacity]'];
            if (!busy) selectors.push('[data-admin-panel="bookings"]', '[data-admin-panel="guests"]');
            selectors.forEach(selector => {
                const old = document.querySelector(selector), next = incoming.querySelector(selector);
                if (old && next) old.replaceWith(next);
            });
            activate(active);
            setFloor();
            indicator.textContent = 'განახლდა ' + new Date().toLocaleTimeString('ka-GE');
        } catch {
            indicator.textContent = 'კავშირი შეწყდა · ვცდილობთ ხელახლა';
        } finally { clearTimeout(timeout); refreshing = false; }
    };
    setInterval(refresh, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); });
})();
