(() => {
    const tabs = [...document.querySelectorAll('[data-admin-tab]')];
    const panels = [...document.querySelectorAll('[data-admin-panel]')];
    if (!tabs.length) return;

    const activate = (name) => {
        tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.adminTab === name));
        panels.forEach(panel => panel.hidden = panel.dataset.adminPanel !== name);
        if (history.replaceState) history.replaceState(null, '', '#' + name);
    };

    tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.adminTab)));

    const initial = location.hash.replace('#', '');
    if (tabs.some(tab => tab.dataset.adminTab === initial)) activate(initial);
})();
