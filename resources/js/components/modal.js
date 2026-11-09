const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

const stack = [];

function trap(event) {
    const top = stack[stack.length - 1];
    if (!top) return;
    if (event.key === 'Escape' && top.dataset.static !== 'true') {
        if (document.querySelector('.select2-container--open')) return;
        event.preventDefault();
        close(top);
        return;
    }
    if (event.key !== 'Tab') return;
    const items = [...top.querySelectorAll(FOCUSABLE)].filter((el) => el.offsetParent !== null);
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const CLOSE_MS = 160;

export function open(target) {
    const el = typeof target === 'string' ? document.getElementById(target) : target;
    if (!el) return el;
    if (el.dataset.state === 'closing') {
        clearTimeout(el._closeTimer);
    } else if (!el.hidden) {
        return el;
    } else {
        el._returnFocus = document.activeElement;
        el.hidden = false;
        void el.offsetWidth;
    }
    el.dataset.state = 'open';
    document.body.classList.add('overflow-hidden');
    if (!stack.includes(el)) stack.push(el);
    if (stack.length === 1) document.addEventListener('keydown', trap, true);
    requestAnimationFrame(() => {
        const autofocus = el.querySelector('[autofocus]') || el.querySelector(FOCUSABLE);
        autofocus?.focus({ preventScroll: true });
    });
    el.dispatchEvent(new CustomEvent('modal:open', { bubbles: true }));
    return el;
}

export function close(target) {
    const el = typeof target === 'string' ? document.getElementById(target) : target;
    if (!el || el.hidden || el.dataset.state === 'closing') return;
    el.dataset.state = 'closing';
    stack.splice(stack.indexOf(el), 1);
    if (!stack.length) document.removeEventListener('keydown', trap, true);
    el._returnFocus?.focus?.({ preventScroll: true });
    el.dispatchEvent(new CustomEvent('modal:close', { bubbles: true }));

    el._closeTimer = setTimeout(() => {
        el.hidden = true;
        delete el.dataset.state;
        if (!stack.length) document.body.classList.remove('overflow-hidden');
        el.dispatchEvent(new CustomEvent('modal:closed', { bubbles: true }));
    }, reducedMotion.matches ? 0 : CLOSE_MS);
}

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal-open]');
    if (opener) {
        event.preventDefault();
        open(opener.dataset.modalOpen);
        return;
    }
    const closer = event.target.closest('[data-modal-close]');
    if (closer) {
        event.preventDefault();
        close(closer.closest('[data-modal]'));
        return;
    }
    if (event.target.matches('[data-modal-backdrop]')) {
        const dialog = event.target.closest('[data-modal]');
        if (dialog?.dataset.static !== 'true') close(dialog);
    }
});

export const modal = { open, close };
