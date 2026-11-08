/**
 * Small hover/focus tooltip. Text comes from data-tooltip, or from aria-label
 * when the control has no visible text. A disabled control with
 * data-disabled-reason shows that reason instead.
 */
const SELECTOR = '[data-disabled-reason], .select2-container, [data-tooltip], [data-tooltip-collapsed], button[aria-label], a[aria-label], [role="button"][aria-label], label[aria-label]';
const GAP = 8;

let tip;
let current;

function isDisabled(el) {
    return el.disabled || el.getAttribute('aria-disabled') === 'true' || !!el.closest('fieldset:disabled');
}

function disabledReason(el) {
    if (el.classList.contains('select2-container')) {
        const select = el.previousElementSibling;
        return select?.tagName === 'SELECT' && select.disabled ? select.dataset.disabledReason || null : null;
    }
    const control = el.matches('label') ? el.querySelector('input, select, button') : el;
    return control && isDisabled(control) ? control.dataset.disabledReason || el.dataset.disabledReason || null : null;
}

function textFor(el) {
    const reason = disabledReason(el);
    if (reason) return reason;
    if (el.classList.contains('select2-container')) return null;
    if (el.dataset.tooltipCollapsed !== undefined) {
        return document.documentElement.dataset.sidebar === 'collapsed' && window.innerWidth >= 1024 ? el.dataset.tooltipCollapsed : null;
    }
    if (el.dataset.tooltip !== undefined) return el.dataset.tooltip || el.getAttribute('aria-label');
    if (el.closest('[data-no-tooltip]')) return null;
    return el.textContent.trim() === '' ? el.getAttribute('aria-label') : null;
}

function place(el) {
    const r = el.getBoundingClientRect();
    const t = tip.getBoundingClientRect();
    if (el.dataset.tooltipCollapsed !== undefined) {
        tip.style.top = `${r.top + r.height / 2 - t.height / 2}px`;
        tip.style.left = `${r.right + GAP}px`;
        tip.dataset.side = 'right';
        return;
    }
    const above = r.top - t.height - GAP >= 4;
    const top = above ? r.top - t.height - GAP : r.bottom + GAP;
    const left = Math.min(Math.max(4, r.left + r.width / 2 - t.width / 2), window.innerWidth - t.width - 4);
    tip.style.top = `${top}px`;
    tip.style.left = `${left}px`;
    tip.dataset.side = above ? 'top' : 'bottom';
}

function show(el) {
    const text = textFor(el);
    if (!text) return;
    current = el;
    tip.textContent = text;
    tip.hidden = false;
    el.setAttribute('aria-describedby', 'app-tooltip');
    place(el);
    requestAnimationFrame(() => tip.classList.add('is-visible'));
}

function hide() {
    if (!current) return;
    current.removeAttribute('aria-describedby');
    current = null;
    tip.classList.remove('is-visible');
    tip.hidden = true;
}

export function initTooltips() {
    tip = document.createElement('div');
    tip.id = 'app-tooltip';
    tip.className = 'tooltip';
    tip.setAttribute('role', 'tooltip');
    tip.hidden = true;
    document.body.appendChild(tip);

    document.addEventListener('pointerover', (e) => {
        if (e.pointerType === 'touch') return;
        const el = e.target.closest?.(SELECTOR);
        if (el && el !== current) show(el);
        else if (!el && current) hide();
    });
    document.addEventListener('pointerout', (e) => {
        if (current && !current.contains(e.relatedTarget)) hide();
    });
    document.addEventListener('focusin', (e) => {
        const el = e.target.closest?.(SELECTOR);
        if (el && el.matches(':focus-visible')) show(el);
    });
    document.addEventListener('focusout', hide);
    ['click', 'keydown', 'scroll'].forEach((name) => document.addEventListener(name, (e) => {
        if (name !== 'keydown' || e.key === 'Escape') hide();
    }, true));
    window.addEventListener('resize', hide);
}
