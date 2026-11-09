import $ from 'jquery';
import { http } from '../core/http';
import { confirmDialog } from './confirm';
import { toast } from './toast';

/**
 * <div data-ajax-table data-url="..."> loads server-rendered rows.
 * The endpoint returns { data: { html }, meta: { total, from, to, current_page, last_page } }.
 * Children:
 *   [data-table-search]      text input, debounced
 *   [data-table-filter]      any input/select; its name becomes a query param
 *   [data-sort="key"]        sortable header button
 *   [data-table-body]        receives html
 *   [data-table-pagination]  receives pager
 *   [data-table-summary]     receives "x–y of z"
 *   [data-table-export=url]  link that downloads url with the current filters
 *   [data-table-view]        Table / Cards switch; html goes to [data-table-cards] in card view and the
 *                            choice is saved to the user's preferences (data-view-key, data-view-url)
 *   [data-table-stats]       receives data.stats when the endpoint sends it
 *   [data-table-preset]      summary tile; clicking applies its JSON filters, clicking again clears them
 *   [data-table-history]     keeps the filters in the address bar (back/forward and shared links restore them)
 * Row actions:
 *   [data-row-action="delete|post"] with data-url, optional data-confirm.
 * Other scripts can call el.reload() or dispatch "table:reload".
 */
export function initAjaxTables(root = document) {
    root.querySelectorAll('[data-ajax-table]').forEach(setup);
}

function setup(table) {
    if (table._ajaxTable) return;
    const state = { page: 1, sort: table.dataset.defaultSort || '', dir: table.dataset.defaultDir || 'asc', controller: null };
    table._ajaxTable = state;

    const body = table.querySelector('[data-table-body]');
    const pager = table.querySelector('[data-table-pagination]');
    const summary = table.querySelector('[data-table-summary]');
    const skeleton = body.innerHTML;
    const cards = table.querySelector('[data-table-cards]');
    const grid = table.querySelector('[data-table-grid]');
    const viewInput = table.querySelector('[data-table-view-input]');
    const stats = table.querySelector('[data-table-stats]');
    const target = () => (cards && viewInput?.value === 'cards' ? cards : body);

    // Plain filters can be preset from the page URL, e.g. /expenses?status=submitted.
    const preset = new URLSearchParams(window.location.search);
    table.querySelectorAll('select[data-table-filter]:not([data-select]), input[data-table-filter]').forEach((input) => {
        if (input.name && preset.has(input.name)) input.value = preset.get(input.name);
    });

    const params = () => {
        const query = { page: state.page };
        if (state.sort) Object.assign(query, { sort: state.sort, dir: state.dir });
        table.querySelectorAll('[data-table-search], [data-table-filter]').forEach((input) => {
            if (input.value !== '' && input.name) query[input.name] = input.value;
        });
        return query;
    };

    // [data-table-export] links download with the table's current filters and sort.
    table.querySelectorAll('[data-table-export]').forEach((link) => {
        link.addEventListener('click', () => {
            const url = new URL(link.dataset.tableExport, window.location.origin);
            Object.entries(params()).forEach(([key, value]) => key !== 'page' && url.searchParams.set(key, value));
            link.href = url.toString();
        });
    });

    const load = async () => {
        state.controller?.abort();
        state.controller = new AbortController();
        table.setAttribute('aria-busy', 'true');
        if (!body.children.length) body.innerHTML = skeleton;
        try {
            const res = await http.get(table.dataset.url, params(), { signal: state.controller.signal });
            target().innerHTML = res.data.html;
            if (table.hasAttribute('data-table-history')) {
                const url = new URL(window.location.href);
                url.search = new URLSearchParams(Object.entries(params()).filter(([k, v]) => k !== 'page' && v !== '')).toString();
                window.history.replaceState(null, '', url);
            }
            if (stats && res.data.stats !== undefined) stats.innerHTML = res.data.stats;
            renderMeta(res.meta || {});
            table.dispatchEvent(new CustomEvent('table:loaded', { bubbles: true, detail: res }));
        } catch (error) {
            if (error.status !== 0) body.innerHTML = '';
        } finally {
            table.removeAttribute('aria-busy');
        }
    };

    const renderMeta = (meta) => {
        if (summary) summary.textContent = meta.total ? `${meta.from}–${meta.to} of ${meta.total}` : '';
        if (!pager) return;
        pager.innerHTML = '';
        if (!meta.last_page || meta.last_page <= 1) return;
        const button = (label, page, disabled, current = false) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = `btn btn-sm ${current ? 'btn-primary' : 'btn-ghost'}`;
            b.textContent = label;
            b.disabled = disabled;
            if (current) b.setAttribute('aria-current', 'page');
            b.addEventListener('click', () => {
                state.page = page;
                load();
            });
            return b;
        };
        pager.append(button('Previous', meta.current_page - 1, meta.current_page <= 1));
        pages(meta.current_page, meta.last_page).forEach((p) => {
            if (p === '…') {
                const s = document.createElement('span');
                s.className = 'px-1 text-ink-400';
                s.textContent = '…';
                pager.append(s);
            } else pager.append(button(String(p), p, false, p === meta.current_page));
        });
        pager.append(button('Next', meta.current_page + 1, meta.current_page >= meta.last_page));
    };

    let timer;
    table.querySelectorAll('[data-table-search]').forEach((input) =>
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                state.page = 1;
                load();
            }, 300);
        }),
    );
    $(table).on('change', '[data-table-filter]', () => {
        state.page = 1;
        load();
    });

    table.addEventListener('click', async (event) => {
        const switcher = event.target.closest('[data-table-view]');
        if (switcher && viewInput) {
            const mode = switcher.dataset.tableView;
            if (viewInput.value === mode) return;
            viewInput.value = mode;
            table.querySelectorAll('[data-table-view]').forEach((b) => {
                b.classList.toggle('is-active', b === switcher);
                b.setAttribute('aria-pressed', String(b === switcher));
            });
            cards.hidden = mode !== 'cards';
            grid.hidden = mode === 'cards';
            (mode === 'cards' ? body : cards).innerHTML = '';
            load();
            http.put(table.dataset.viewUrl, { key: table.dataset.viewKey, value: mode }, { silent: true }).catch(() => {});
            return;
        }

        const tile = event.target.closest('[data-table-preset]');
        if (tile && table.contains(tile)) {
            const clear = tile.getAttribute('aria-pressed') === 'true';
            Object.entries(JSON.parse(tile.dataset.tablePreset || '{}')).forEach(([name, value]) => {
                const input = table.querySelector(`[data-table-filter][name="${name}"]`);
                if (input) $(input).val(clear ? '' : value);
            });
            state.page = 1;
            load();
            return;
        }

        const sorter = event.target.closest('[data-sort]');
        if (sorter && table.contains(sorter)) {
            const key = sorter.dataset.sort;
            state.dir = state.sort === key && state.dir === 'asc' ? 'desc' : 'asc';
            state.sort = key;
            table.querySelectorAll('[data-sort]').forEach((el) =>
                el.closest('th')?.setAttribute('aria-sort', el === sorter ? (state.dir === 'asc' ? 'ascending' : 'descending') : 'none'),
            );
            load();
            return;
        }

        const toggle = event.target.closest('[data-toggle-row]');
        if (toggle && table.contains(toggle)) {
            const detail = toggle.closest('tr').nextElementSibling;
            if (detail?.matches('[data-detail-row]')) {
                detail.hidden = !detail.hidden;
                toggle.setAttribute('aria-expanded', String(!detail.hidden));
                toggle.querySelector('svg')?.classList.toggle('rotate-180', !detail.hidden);
            }
            return;
        }

        const action = event.target.closest('[data-row-action]');
        if (!action || !table.contains(action)) return;
        event.preventDefault();
        if (action.dataset.confirm) {
            const ok = await confirmDialog({
                title: action.dataset.confirm,
                message: action.dataset.confirmMessage || '',
                confirmText: action.dataset.confirmText || 'Confirm',
                tone: action.dataset.tone || 'danger',
            });
            if (!ok) return;
        }
        action.disabled = true;
        try {
            const method = action.dataset.rowAction === 'delete' ? 'delete' : 'post';
            const res = await http[method](action.dataset.url, {});
            if (res.message) toast.success(res.message);
            if (res.data?.redirect) {
                window.location.assign(res.data.redirect);
                return;
            }
            load();
            table.dispatchEvent(new CustomEvent('table:action', { bubbles: true, detail: res }));
        } catch {
            action.disabled = false;
        }
    });

    table.reload = load;
    table.addEventListener('table:reload', load);

    // Tables inside a hidden tab panel load the first time the panel is shown.
    if (table.closest('[hidden]')) {
        const onShown = () => {
            if (table.closest('[hidden]')) return;
            document.removeEventListener('tab:shown', onShown);
            load();
        };
        document.addEventListener('tab:shown', onShown);
    } else {
        load();
    }
}

function pages(current, last) {
    const out = [];
    for (let p = 1; p <= last; p++) {
        if (p === 1 || p === last || Math.abs(p - current) <= 1) out.push(p);
        else if (out[out.length - 1] !== '…') out.push('…');
    }
    return out;
}
