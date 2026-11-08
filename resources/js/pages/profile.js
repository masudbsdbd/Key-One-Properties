function showCodes(card, codes) {
    const box = card.querySelector('[data-2fa-codes]');
    const list = box.querySelector('[data-2fa-code-list]');
    list.innerHTML = '';
    codes.forEach((code) => {
        const li = document.createElement('li');
        li.className = 'rounded-md bg-surface px-2 py-1';
        li.textContent = code;
        list.append(li);
    });
    box.hidden = false;
}

export function initProfilePage() {
    const card = document.querySelector('[data-two-factor-card]');
    if (!card) return;

    card.addEventListener('ajax:success', (event) => {
        const form = event.target;
        const data = event.detail.data || {};

        if (form.matches('[data-two-factor-enable]')) {
            card.querySelector('[data-2fa-start]').hidden = true;
            card.querySelector('[data-2fa-setup]').hidden = false;
            card.querySelector('[data-2fa-qr]').innerHTML = data.qr;
            card.querySelector('[data-2fa-secret]').textContent = data.secret;
            card.querySelector('#two-factor-code').focus();
        }

        if (form.matches('[data-two-factor-confirm]')) {
            card.querySelector('[data-2fa-setup]').hidden = true;
            showCodes(card, data.recovery_codes || []);
        }
    });

    document.addEventListener('ajax:success', (event) => {
        if (event.target.matches('[data-two-factor-recovery]')) showCodes(card, event.detail.data.recovery_codes || []);
        if (event.target.matches('[data-reload]')) window.location.reload();
    });
}
