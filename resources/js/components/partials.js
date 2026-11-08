import { http } from '../core/http';
import { initSelects } from './select';

/**
 * [data-partial-url] blocks re-render from the server after any successful
 * change on the page (AJAX form, row action or action button).
 */
async function reload(el) {
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

export function initPartials() {
    document.querySelectorAll('[data-partial-url][data-lazy]').forEach(reload);

    ['data:changed', 'action:success', 'table:action'].forEach((name) => document.addEventListener(name, reloadPartials));
    document.addEventListener('ajax:success', (event) => {
        if (event.target.closest('[data-partial-url]') || event.target.hasAttribute('data-refresh-partials')) {
            reloadPartials();
        }
    });
}
