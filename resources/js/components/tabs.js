/**
 * [role=tablist] with [role=tab][aria-controls]. Keeps the active tab in the
 * URL hash so reloads and links land on the same tab.
 */
export function initTabs(root = document) {
    root.querySelectorAll('[role="tablist"]').forEach((list) => {
        const tabs = [...list.querySelectorAll('[role="tab"]')];
        const select = (tab, focus = false) => {
            tabs.forEach((t) => {
                const active = t === tab;
                t.setAttribute('aria-selected', String(active));
                t.tabIndex = active ? 0 : -1;
                const panel = document.getElementById(t.getAttribute('aria-controls'));
                if (panel) panel.hidden = !active;
            });
            if (focus) tab.focus();
            if (list.dataset.hash !== 'false') history.replaceState(null, '', `#${tab.dataset.tab || tab.id}`);
            tab.dispatchEvent(new CustomEvent('tab:shown', { bubbles: true }));
        };

        tabs.forEach((tab, i) => {
            tab.addEventListener('click', () => select(tab));
            tab.addEventListener('keydown', (e) => {
                const next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
                if (next === undefined) return;
                e.preventDefault();
                select(tabs[(next + tabs.length) % tabs.length], true);
            });
        });

        const fromHash = tabs.find((t) => `#${t.dataset.tab || t.id}` === window.location.hash);
        select(fromHash || tabs.find((t) => t.getAttribute('aria-selected') === 'true') || tabs[0]);

        if (list.dataset.hash !== 'false') {
            window.addEventListener('hashchange', () => {
                const tab = tabs.find((t) => `#${t.dataset.tab || t.id}` === window.location.hash);
                if (!tab) return;
                select(tab);
                list.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    });
}
