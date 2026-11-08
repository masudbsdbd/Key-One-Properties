import $ from 'jquery';
import { http } from '../core/http';
import { initSelects } from './select';

/**
 * [data-partial-url] blocks re-render from the server after any successful
 * change on the page (AJAX form, row action or action button).
 */
async function reload(el) {
    if (!el.dataset.partialUrl) return;
    try {
        el.setAttribute('aria-busy', 'true');
        const res = await http.get(el.dataset.partialUrl, {}, { silent: true });
        el.innerHTML = res.data.html;
        initSelects(el);
        el.dispatchEvent(new CustomEvent('partial:loaded', { bubbles: true }));
    } catch {
        /* keep the previous content */
    } finally {
        el.removeAttribute('aria-busy');
    }
}

export function reloadPartials() {
    document.querySelectorAll('[data-partial-url]').forEach(reload);
}

/**
 * [data-partial-source="#field"][data-partial-base="url"] loads url?param=value
 * whenever the field changes (param from data-partial-param, default "parent").
 */
function loadFromSource(el, value) {
    if (!value) {
        delete el.dataset.partialUrl;
        el.innerHTML = el.dataset.partialEmpty ? `<p class="p-5 text-sm text-ink-500">${el.dataset.partialEmpty}</p>` : '';
        return;
    }
    const url = new URL(el.dataset.partialBase, window.location.origin);
    url.searchParams.set(el.dataset.partialParam || 'parent', value);
    el.dataset.partialUrl = url.toString();
    reload(el);
}

export function initPartials() {
    $(document).on('change', 'select[id], input[id]', function () {
        document.querySelectorAll(`[data-partial-source="#${CSS.escape(this.id)}"]`).forEach((el) => loadFromSource(el, $(this).val()));
    });
    document.addEventListener('remote:loaded', (event) => {
        event.target.querySelectorAll?.('[data-partial-source]').forEach((el) => {
            const source = document.querySelector(el.dataset.partialSource);
            if (source && $(source).val()) loadFromSource(el, $(source).val());
        });
    });

    document.querySelectorAll('[data-partial-url][data-lazy]').forEach(reload);

    ['data:changed', 'action:success', 'table:action'].forEach((name) => document.addEventListener(name, reloadPartials));
    document.addEventListener('ajax:success', (event) => {
        if (event.target.closest('[data-partial-url]') || event.target.hasAttribute('data-refresh-partials')) {
            reloadPartials();
        }
    });
}
