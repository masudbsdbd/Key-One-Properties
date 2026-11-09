import $ from 'jquery';
import { http } from '../core/http';

const pad = (n) => String(n).padStart(2, '0');
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

function range(preset) {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    switch (preset) {
        case 'this-month':
            return [new Date(y, m, 1), new Date(y, m + 1, 0)];
        case 'last-month':
            return [new Date(y, m - 1, 1), new Date(y, m, 0)];
        case 'this-quarter': {
            const q = Math.floor(m / 3) * 3;
            return [new Date(y, q, 1), new Date(y, q + 3, 0)];
        }
        case 'this-year':
            return [new Date(y, 0, 1), new Date(y, 11, 31)];
        default:
            return null;
    }
}

export function initReportPages() {
    initExecutive();
    document.querySelectorAll('[data-period-preset]').forEach((select) => {
        const scope = select.closest('[data-ajax-table], [data-executive]') || document;
        const from = scope.querySelector('[name=from]');
        const to = scope.querySelector('[name=to]');
        const params = new URLSearchParams(window.location.search);
        if (params.get('from') || params.get('to')) select.value = 'custom';
        select.addEventListener('change', () => {
            if (select.value === 'custom') return;
            const r = range(select.value);
            from.value = r ? iso(r[0]) : '';
            to.value = r ? iso(r[1]) : '';
            $(to).trigger('change');
        });
        [from, to].forEach((input) => input?.addEventListener('input', () => { select.value = 'custom'; }));
    });
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
