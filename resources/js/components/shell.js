const KEY = 'keyone.sidebar';

function store(value) {
    try {
        localStorage.setItem(KEY, value);
    } catch {
        /* storage unavailable */
    }
}

function initNavGroups() {
    let open = {};
    try {
        open = JSON.parse(localStorage.getItem('keyone.nav') || '{}');
    } catch {
        open = {};
    }

    document.querySelectorAll('[data-nav-list]').forEach((list) => {
        const key = list.dataset.navList;
        const button = document.querySelector(`[data-nav-group="${key}"]`);
        const expanded = list.dataset.current === '1' || open[key] !== false;
        list.hidden = !expanded;
        button?.setAttribute('aria-expanded', String(expanded));
    });

    const save = () => {
        try {
            localStorage.setItem('keyone.nav', JSON.stringify(open));
        } catch {
            /* storage unavailable */
        }
    };
    const setGroup = (key, expanded) => {
        const list = document.querySelector(`[data-nav-list="${key}"]`);
        if (!list) return;
        list.hidden = !expanded;
        document.querySelector(`[data-nav-group="${key}"]`)?.setAttribute('aria-expanded', String(expanded));
        open[key] = expanded;
    };
    // One button collapses every group, or expands them all again when none is open.
    const toggleAll = document.querySelector('[data-nav-toggle-all]');
    const syncToggleAll = () => {
        if (!toggleAll) return;
        const anyOpen = [...document.querySelectorAll('[data-nav-list]')].some((l) => !l.hidden);
        toggleAll.setAttribute('aria-label', anyOpen ? 'Collapse all groups' : 'Expand all groups');
        toggleAll.querySelector('[data-nav-icon="collapse"]').hidden = !anyOpen;
        toggleAll.querySelector('[data-nav-icon="expand"]').hidden = anyOpen;
    };
    syncToggleAll();

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-nav-toggle-all]')) {
            const anyOpen = [...document.querySelectorAll('[data-nav-list]')].some((l) => !l.hidden);
            document.querySelectorAll('[data-nav-list]').forEach((l) => setGroup(l.dataset.navList, !anyOpen));
            save();
            syncToggleAll();
            return;
        }
        const button = event.target.closest('[data-nav-group]');
        if (!button) return;
        const key = button.dataset.navGroup;
        setGroup(key, document.querySelector(`[data-nav-list="${key}"]`).hidden);
        save();
        syncToggleAll();
    });
}

export function initShell() {
    const html = document.documentElement;
    initNavGroups();

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-sidebar-toggle]')) {
            if (window.matchMedia('(min-width: 1024px)').matches) {
                const collapsed = html.dataset.sidebar === 'collapsed';
                html.dataset.sidebar = collapsed ? 'expanded' : 'collapsed';
                store(html.dataset.sidebar);
            } else {
                document.body.classList.toggle('sidebar-open');
            }
            return;
        }
        if (event.target.closest('[data-sidebar-close]')) document.body.classList.remove('sidebar-open');

        const menuButton = event.target.closest('[data-menu-button]');
        document.querySelectorAll('[data-menu]').forEach((menu) => {
            const owns = menuButton && menu.id === menuButton.getAttribute('aria-controls');
            if (owns) {
                menu.hidden = !menu.hidden;
                menuButton.setAttribute('aria-expanded', String(!menu.hidden));
            } else if (!menu.contains(event.target)) {
                menu.hidden = true;
                document.querySelector(`[aria-controls="${menu.id}"]`)?.setAttribute('aria-expanded', 'false');
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            const search = document.getElementById('global-search');
            if (search) {
                event.preventDefault();
                search.focus();
            }
        }
        if (event.key === 'Escape') {
            document.querySelectorAll('[data-menu]').forEach((m) => (m.hidden = true));
            document.body.classList.remove('sidebar-open');
        }
    });
}
