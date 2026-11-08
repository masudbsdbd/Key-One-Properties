import { http } from '../core/http';
import { modal } from './modal';
import { initSelects } from './select';
import { initTabs } from './tabs';

/**
 * [data-remote-modal="url"] loads server-rendered HTML ({ data: { html, title } })
 * into the shared #remote-modal. data-size sets the panel width (e.g. max-w-4xl).
 * After a form inside succeeds, every [data-ajax-table] on the page reloads.
 */
export async function openRemoteModal(url, { title = '', size = 'max-w-2xl' } = {}) {
    const dialog = document.getElementById('remote-modal');
    if (!dialog) return;

    const panel = dialog.querySelector('[data-remote-panel]');
    const body = dialog.querySelector('[data-remote-body]');
    panel.className = panel.className.replace(/max-w-\S+/, size);
    dialog.querySelector('[data-remote-title]').textContent = title;
    body.innerHTML = '<div class="space-y-4 py-2"><div class="skeleton h-10 w-full"></div><div class="skeleton h-10 w-full"></div><div class="skeleton h-10 w-2/3"></div></div>';
    modal.open(dialog);

    try {
        const res = await http.get(url);
        if (res.data.title) dialog.querySelector('[data-remote-title]').textContent = res.data.title;
        body.innerHTML = res.data.html;
        initSelects(body);
        initTabs(body);
        (body.querySelector('[autofocus]') || body.querySelector('input:not([type=hidden]), select, textarea'))?.focus();
        body.dispatchEvent(new CustomEvent('remote:loaded', { bubbles: true }));
    } catch {
        modal.close(dialog);
    }
}

export function initRemoteModal() {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-remote-modal]');
        if (!trigger) return;
        event.preventDefault();
        openRemoteModal(trigger.dataset.remoteModal, { title: trigger.dataset.title || '', size: trigger.dataset.size || 'max-w-2xl' });
    });

    document.addEventListener('ajax:success', (event) => {
        if (!event.target.closest('#remote-modal') && !event.target.hasAttribute('data-refresh-tables')) return;
        document.querySelectorAll('[data-ajax-table]').forEach((table) => table.reload?.());
        document.dispatchEvent(new CustomEvent('data:changed', { detail: event.detail }));
    });
}
