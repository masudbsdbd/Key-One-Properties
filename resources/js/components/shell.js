const KEY = 'keyone.sidebar';

function store(value) {
    try {
        localStorage.setItem(KEY, value);
    } catch {
        /* storage unavailable */
    }
}

export function initShell() {
    const html = document.documentElement;

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
