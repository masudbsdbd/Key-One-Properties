const icons = {
    success: '<path d="M5 12.5l4.5 4.5L19 7.5" />',
    error: '<path d="M12 8v5m0 3.5v.01M10.3 3.9L2.6 17.5A2 2 0 004.3 20.5h15.4a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />',
    info: '<path d="M12 16v-4m0-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
};

const tones = {
    success: 'text-success-600 bg-success-50',
    error: 'text-danger-600 bg-danger-50',
    info: 'text-info-600 bg-info-50',
};

function region() {
    let el = document.getElementById('toast-region');
    if (!el) {
        el = document.createElement('div');
        el.id = 'toast-region';
        el.className = 'fixed bottom-4 right-4 z-[70] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2';
        el.setAttribute('aria-live', 'polite');
        document.body.appendChild(el);
    }
    return el;
}

function show(type, message, timeout = 4500) {
    const item = document.createElement('div');
    item.setAttribute('role', type === 'error' ? 'alert' : 'status');
    item.className =
        'flex items-start gap-3 rounded-card border border-line bg-surface p-3.5 shadow-raised transition-all duration-200 translate-y-2 opacity-0';
    item.innerHTML = `
        <span class="icon-tile icon-tile-sm ${tones[type]}">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${icons[type]}</svg>
        </span>
        <p class="flex-1 pt-1 text-sm text-ink-800"></p>
        <button type="button" class="btn btn-ghost btn-icon h-7 w-7" aria-label="Dismiss">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>`;
    item.querySelector('p').textContent = message;

    const remove = () => {
        item.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => item.remove(), 200);
    };
    item.querySelector('button').addEventListener('click', remove);

    region().appendChild(item);
    requestAnimationFrame(() => item.classList.remove('opacity-0', 'translate-y-2'));
    if (timeout) setTimeout(remove, timeout);
}

export const toast = {
    success: (m) => show('success', m),
    error: (m) => show('error', m, 7000),
    info: (m) => show('info', m),
};
