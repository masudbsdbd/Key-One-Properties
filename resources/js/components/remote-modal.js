import { http } from '../core/http';
import { modal } from './modal';
import { initSelects } from './select';
import { initTabs } from './tabs';

const SKELETON = '<div class="space-y-4 py-2"><div class="skeleton h-10 w-full"></div><div class="skeleton h-10 w-full"></div><div class="skeleton h-10 w-2/3"></div></div>';
const SKELETON_DELAY = 150;
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** Replaces the body and eases the panel from its old height to the new one. */
function fill(panel, body, html, animate) {
    const from = panel.offsetHeight;
    body.innerHTML = html;
    initSelects(body);
    initTabs(body);
    if (!animate || reducedMotion.matches) return;
    const to = panel.offsetHeight;
    if (Math.abs(to - from) > 2) {
        panel.style.overflow = 'hidden';
        panel.animate([{ height: `${from}px` }, { height: `${to}px` }], { duration: 240, easing: 'cubic-bezier(0.16, 1, 0.3, 1)' })
            .finished.finally(() => panel.style.removeProperty('overflow'));
    }
    body.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 180, easing: 'ease-out' });
}

/**
 * [data-remote-modal="url"] loads server-rendered HTML ({ data: { html, title } })
 * into the shared #remote-modal. data-size sets the panel width (e.g. max-w-4xl).
 * After a form inside succeeds, every [data-ajax-table] on the page reloads.
 * A fast response opens the modal with its content; a slow one shows a skeleton first.
 */
export async function openRemoteModal(url, { title = '', size = 'max-w-2xl' } = {}) {
    const dialog = document.getElementById('remote-modal');
    if (!dialog) return;

    const token = (dialog._loadToken = {});
    const panel = dialog.querySelector('[data-remote-panel]');
    const body = dialog.querySelector('[data-remote-body]');
    const heading = dialog.querySelector('[data-remote-title]');
    const request = http.get(url).then((res) => ({ res }), (error) => ({ error }));

    const setFrame = () => {
        panel.className = panel.className.replace(/max-w-\S+/, size);
        heading.textContent = title;
    };
    let shown = dialog.dataset.state === 'open';
    const show = (html) => {
        setFrame();
        body.innerHTML = html;
        modal.open(dialog);
        shown = true;
    };

    let early = null;
    if (!shown) {
        early = await Promise.race([request, wait(SKELETON_DELAY).then(() => null)]);
        if (token !== dialog._loadToken) return;
        if (!early) show(SKELETON);
    }
    const animate = shown;

    const { res, error } = early ?? (await request);
    if (token !== dialog._loadToken || (shown && dialog.dataset.state !== 'open')) return;
    if (error) {
        if (shown) modal.close(dialog);
        return;
    }

    if (shown) setFrame();
    else show('');
    if (res.data.title) heading.textContent = res.data.title;
    fill(panel, body, res.data.html, animate);
    (body.querySelector('[autofocus]') || body.querySelector('input:not([type=hidden]), select, textarea'))?.focus({ preventScroll: true });
    body.dispatchEvent(new CustomEvent('remote:loaded', { bubbles: true }));
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
