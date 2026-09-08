(() => {
    const tabs = [...document.querySelectorAll('[data-admin-tab]')];
    const panels = [...document.querySelectorAll('[data-admin-panel]')];

    const activate = (name) => {
        tabs.forEach((tab) => {
            tab.classList.toggle('active', tab.dataset.adminTab === name);
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.adminPanel !== name;
        });
        if (history.replaceState) history.replaceState(null, '', '#' + name);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => activate(tab.dataset.adminTab));
    });

    const initial = location.hash.replace('#', '');
    if (panels.some((panel) => panel.dataset.adminPanel === initial)) {
        activate(initial);
    }

    document.querySelectorAll('[data-row-detail]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const card = button.parentElement.querySelector('.row-detail-card');
            if (!card) return;

            document.querySelectorAll('.row-detail-card').forEach((other) => {
                if (other !== card) other.hidden = true;
            });
            card.hidden = !card.hidden;
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('.row-detail-card').forEach((card) => {
            card.hidden = true;
        });
    });

    document.querySelectorAll('.row-detail-card').forEach((card) => {
        card.addEventListener('click', (event) => event.stopPropagation());
    });
})();
