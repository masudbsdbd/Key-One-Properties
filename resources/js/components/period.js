import $ from 'jquery';

const pad = (n) => String(n).padStart(2, '0');
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

export function periodRange(preset) {
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

/**
 * [data-period-preset] select next to from/to date filters. A preset fills both dates; "Custom" reveals them.
 * Dates coming from the address bar select the matching preset, or Custom.
 */
export function initPeriods(root = document) {
    root.querySelectorAll('[data-period-preset]').forEach((select) => {
        if (select._period) return;
        select._period = true;
        const scope = select.closest('[data-period]') || select.closest('[data-ajax-table], [data-executive]') || document;
        const from = scope.querySelector('[name=from]');
        const to = scope.querySelector('[name=to]');
        const dates = scope.querySelector('[data-period-dates]');
        const params = new URLSearchParams(window.location.search);
        const show = () => { if (dates) dates.hidden = select.value !== 'custom'; };
        const fromUrl = params.get('from') || '';
        const toUrl = params.get('to') || '';
        if (fromUrl || toUrl) {
            if (from && !from.value) from.value = fromUrl;
            if (to && !to.value) to.value = toUrl;
            const match = ['this-month', 'last-month', 'this-quarter', 'this-year'].find((p) => {
                const r = periodRange(p);
                return iso(r[0]) === fromUrl && iso(r[1]) === toUrl;
            });
            select.value = match || 'custom';
        }
        show();
        select.addEventListener('change', () => {
            show();
            if (select.value === 'custom') return;
            const r = periodRange(select.value);
            if (from) from.value = r ? iso(r[0]) : '';
            if (to) to.value = r ? iso(r[1]) : '';
            $(to || from).trigger('change');
        });
        [from, to].forEach((input) => input?.addEventListener('input', () => { select.value = 'custom'; show(); }));
    });
}
