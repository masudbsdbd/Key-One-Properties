function startCountdown(form, seconds) {
    const box = document.querySelector('[data-lockout]');
    const timer = document.querySelector('[data-lockout-timer]');
    const submit = form.querySelector('[type="submit"]');
    let remaining = Math.max(0, Math.ceil(seconds));
    if (!remaining) return;

    clearInterval(form._lockoutTimer);
    const render = () => {
        const m = String(Math.floor(remaining / 60)).padStart(2, '0');
        const s = String(remaining % 60).padStart(2, '0');
        if (timer) timer.textContent = `${m}:${s}`;
    };

    box.hidden = false;
    submit.disabled = true;
    submit.dataset.disabledReason = 'Too many failed attempts. Wait for the timer.';
    form.dataset.locked = 'true';
    render();

    form._lockoutTimer = setInterval(() => {
        remaining -= 1;
        render();
        if (remaining <= 0) {
            clearInterval(form._lockoutTimer);
            box.hidden = true;
            submit.disabled = false;
            form.dataset.locked = 'false';
        }
    }, 1000);
}

export function initAuthPages() {
    const form = document.querySelector('[data-lockout-form]');
    if (form) {
        startCountdown(form, Number(form.dataset.lockedFor || 0));
        form.addEventListener('ajax:error', (event) => {
            const error = event.detail;
            if (error.status === 429) startCountdown(form, Number(error.payload.retry_after || 0));
        });
        form.addEventListener('ajax:done', () => {
            if (form.dataset.locked === 'true') {
                const submit = form.querySelector('[type="submit"]');
                submit.disabled = true;
                submit.dataset.disabledReason = 'Too many failed attempts. Wait for the timer.';
            }
        });
    }

    const twoFactor = document.querySelector('[data-two-factor-form]');
    if (twoFactor) {
        const toggle = twoFactor.querySelector('[data-toggle-recovery]');
        toggle.addEventListener('click', () => {
            const code = twoFactor.querySelector('[data-mode="code"]');
            const recovery = twoFactor.querySelector('[data-mode="recovery"]');
            const useRecovery = recovery.hidden;
            code.hidden = useRecovery;
            recovery.hidden = !useRecovery;
            code.querySelector('input').value = '';
            recovery.querySelector('input').value = '';
            toggle.textContent = useRecovery ? 'Use authenticator code' : 'Use a recovery code';
            (useRecovery ? recovery : code).querySelector('input').focus();
        });
    }
}

document.addEventListener('ajax:success', (event) => {
    if (!event.target.matches('[data-password-request]')) return;
    const box = document.querySelector('[data-request-sent]');
    if (box) box.hidden = false;
});
