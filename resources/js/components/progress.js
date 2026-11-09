/**
 * Thin bar along the top of the window: runs while a page loads, before leaving for another page,
 * and while any non-silent request is in flight. Requests are counted, so overlapping ones share one bar.
 */
let pending = 0;
let value = 0;
let trickle;
let fallback;

const bar = () => document.querySelector('[data-page-progress]');

function show(v) {
    const el = bar();
    if (!el) return;
    value = Math.min(v, 99);
    el.classList.remove('is-loading', 'is-done');
    el.style.width = `${value}%`;
}

function begin() {
    show(Math.max(value, 10));
    clearInterval(trickle);
    trickle = setInterval(() => show(value + (90 - value) * 0.08), 250);
}

function finish() {
    clearInterval(trickle);
    clearTimeout(fallback);
    const el = bar();
    if (!el) return;
    el.classList.remove('is-loading');
    el.style.width = '100%';
    el.classList.add('is-done');
    setTimeout(() => {
        if (pending) return;
        el.classList.remove('is-done');
        el.style.width = '0%';
        value = 0;
    }, 450);
}

export const progress = {
    start() {
        pending += 1;
        if (pending === 1) begin();
    },
    done() {
        pending = Math.max(0, pending - 1);
        if (!pending) finish();
    },
    reset() {
        pending = 0;
        finish();
    },
};

/** Leaving the page: start, but finish anyway if the page stays (a download, a cancelled navigation). */
function navigating() {
    begin();
    clearTimeout(fallback);
    fallback = setTimeout(() => progress.reset(), 8000);
}

export function initPageProgress() {
    const el = bar();
    if (el?.classList.contains('is-loading')) value = 60;
    if (document.readyState === 'complete') progress.reset();
    else window.addEventListener('load', () => progress.reset(), { once: true });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if ((link.target && link.target !== '_self') || link.hasAttribute('download') || link.hasAttribute('data-remote-modal')) return;
        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || url.protocol.startsWith('javascript')) return;
        if (url.pathname === window.location.pathname && url.search === window.location.search) return;
        navigating();
    });
    document.addEventListener('submit', (event) => {
        if (!event.defaultPrevented && !event.target.hasAttribute('data-ajax-form')) navigating();
    });
    window.addEventListener('beforeunload', navigating);
    window.addEventListener('pageshow', (event) => { if (event.persisted) progress.reset(); });
}
