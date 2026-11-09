import $ from 'jquery';
import { http } from '../core/http';
import { initPeriods } from '../components/period';

function initExports() {
    // Queue opens a modal carrying the table's current filters.
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-queue-export]');
        if (!button) return;
        const table = button.closest('[data-ajax-table]');
        const url = new URL(button.dataset.url, window.location.origin);
        table?.querySelectorAll('[data-table-filter], [data-table-search]').forEach((el) => {
            if (el.name && $(el).val()) url.searchParams.set(el.name, $(el).val());
        });
        button.dataset.remoteModal = url.toString();
        button.dataset.size = 'max-w-md';
    }, true);

    const table = document.querySelector('[data-exports-poll]');
    if (!table) return;
    table.addEventListener('table:loaded', () => {
        if (table.querySelector('[data-run-pending]')) setTimeout(() => table.reload?.(), 5000);
    });
}

export function initReportPages() {
    initExecutive();
    initExports();
    initPeriods();
}

function initExecutive() {
    const root = document.querySelector('[data-executive]');
    if (!root) return;
    const controllers = new Map();
    const params = () => {
        const q = {};
        root.querySelectorAll('[data-exec-filter]').forEach((el) => { if (el.name && $(el).val()) q[el.name] = $(el).val(); });
        return q;
    };
    const load = (card) => {
        const name = card.dataset.widget;
        controllers.get(name)?.abort();
        const controller = new AbortController();
        controllers.set(name, controller);
        card.setAttribute('aria-busy', 'true');
        http.get(`${root.dataset.widgetBase}/${name}`, params(), { signal: controller.signal, silent: true })
            .then((res) => { card.querySelector('[data-widget-body]').innerHTML = res.data.html; })
            .catch((error) => {
                if (error.status === 0) return;
                card.querySelector('[data-widget-body]').innerHTML = '<p class="text-sm text-danger-600">Could not load</p>';
            })
            .finally(() => card.removeAttribute('aria-busy'));
    };
    const loadAll = () => {
        root.querySelectorAll('[data-widget]').forEach(load);
        const url = new URL(window.location.href);
        url.search = new URLSearchParams(params()).toString();
        window.history.replaceState(null, '', url);
    };
    $(root).on('change', '[data-exec-filter]', loadAll);
    root.querySelectorAll('[data-widget]').forEach(load);
}
