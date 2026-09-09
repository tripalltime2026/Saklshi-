(() => {
    document.querySelectorAll('[data-menu-browser]').forEach(root => {
        const category = root.querySelector('[data-menu-category]');
        const search = root.querySelector('[data-menu-search]');
        const selectedButton = root.querySelector('[data-menu-selected]');
        const entries = [...root.querySelectorAll('[data-menu-entry]')];
        const groups = [...root.querySelectorAll('[data-menu-group]')];
        const previous = root.querySelector('[data-menu-prev]');
        const next = root.querySelector('[data-menu-next]');
        let selectedOnly = false;
        let page = 0;
        const size = 8;
        const normalize = text => text.toLocaleLowerCase().normalize('NFKC').trim();
        const render = () => {
            const term = normalize(search.value);
            const matching = entries.filter(row => {
                const selected = Number(row.querySelector('[data-qty-input]')?.value || 0) > 0;
                return (!selectedOnly || selected)
                    && (term || selectedOnly || row.dataset.category === category.value)
                    && (!term || normalize(row.dataset.search).includes(term));
            });
            const pages = Math.max(1, Math.ceil(matching.length / size));
            page = Math.min(page, pages - 1);
            const visible = new Set(matching.slice(page * size, (page + 1) * size));
            entries.forEach(row => { row.hidden = !visible.has(row); });
            groups.forEach(group => { group.hidden = ![...group.querySelectorAll('[data-menu-entry]')].some(row => visible.has(row)); });
            root.querySelector('[data-menu-no-results]').hidden = matching.length !== 0;
            root.querySelector('[data-menu-no-results]').textContent = selectedOnly ? 'მენიუ ჯერ არ აგირჩევიათ.' : 'პოზიცია ვერ მოიძებნა.';
            root.querySelector('[data-menu-result]').textContent = matching.length + ' პოზიცია' + (term ? ' · ყველა კატეგორიაში' : '');
            root.querySelector('[data-menu-pagination]').hidden = pages < 2;
            root.querySelector('[data-menu-page]').textContent = (page + 1) + ' / ' + pages;
            previous.disabled = page === 0;
            next.disabled = page + 1 >= pages;
            category.disabled = selectedOnly || Boolean(term);
            selectedButton?.setAttribute('aria-pressed', String(selectedOnly));
        };
        category.addEventListener('change', () => { page = 0; render(); });
        search.addEventListener('input', () => { page = 0; render(); });
        selectedButton?.addEventListener('click', () => { selectedOnly = !selectedOnly; page = 0; render(); });
        previous.addEventListener('click', () => { page = Math.max(0, page - 1); render(); });
        next.addEventListener('click', () => { page += 1; render(); });
        document.addEventListener('menu:changed', () => { if (selectedOnly) render(); });
        render();
    });
})();
