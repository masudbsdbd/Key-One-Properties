import { toast } from './toast';
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
                if (!menu.hidden) float(menu, menuButton);
            } else if (!menu.contains(event.target) || event.target.closest('[data-remote-modal], [data-action], [data-row-action], a[href]')) {
                menu.hidden = true;
                document.querySelector(`[aria-controls="${menu.id}"]`)?.setAttribute('aria-expanded', 'false');
            }
        });
    });

    // Row menus sit inside scrolling tables, so they are placed on the viewport and follow their button.
    const follow = () => document.querySelectorAll('[data-menu-float]:not([hidden])').forEach((menu) => {
        const button = document.querySelector(`[aria-controls="${menu.id}"]`);
        const box = button?.getBoundingClientRect();
        if (box && box.bottom > 0 && box.top < window.innerHeight) {
            float(menu, button);
        } else {
            menu.hidden = true;
            button?.setAttribute('aria-expanded', 'false');
        }
    });
    window.addEventListener('scroll', follow, { capture: true, passive: true });
    window.addEventListener('resize', follow);

    // [data-copy="text"] puts the text on the clipboard.
    document.addEventListener('click', async (event) => {
        const copy = event.target.closest('[data-copy]');
        if (!copy) return;
        try {
            await navigator.clipboard.writeText(copy.dataset.copy);
            toast.success('Copied');
        } catch {
            toast.error('Could not copy');
        }
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

function float(menu, button) {
    if (!menu.hasAttribute('data-menu-float')) return;
    const anchor = button.getBoundingClientRect();
    const { offsetWidth: width, offsetHeight: height } = menu;
    const below = anchor.bottom + 6 + height <= window.innerHeight;
    menu.style.top = `${below ? anchor.bottom + 6 : Math.max(8, anchor.top - height - 6)}px`;
    menu.style.left = `${Math.max(8, Math.min(anchor.right - width, window.innerWidth - width - 8))}px`;
}
