import { modal } from './modal';

/**
 * Promise-based confirmation using the shared #confirm-dialog markup.
 * Resolves true when confirmed, false otherwise.
 */
export function confirmDialog({ title, message = '', confirmText = 'Confirm', tone = 'danger' } = {}) {
    const dialog = document.getElementById('confirm-dialog');
    if (!dialog) return Promise.resolve(window.confirm(title));

    dialog.querySelector('[data-confirm-title]').textContent = title;
    const body = dialog.querySelector('[data-confirm-message]');
    body.textContent = message;
    body.hidden = !message;

    const accept = dialog.querySelector('[data-confirm-accept]');
    accept.textContent = confirmText;
    accept.className = `btn ${tone === 'danger' ? 'btn-danger' : 'btn-primary'}`;

    return new Promise((resolve) => {
        let settled = false;
        const finish = (value) => {
            if (settled) return;
            settled = true;
            accept.removeEventListener('click', onAccept);
            dialog.removeEventListener('modal:close', onClose);
            resolve(value);
        };
        const onAccept = () => {
            finish(true);
            modal.close(dialog);
        };
        const onClose = () => finish(false);

        accept.addEventListener('click', onAccept);
        dialog.addEventListener('modal:close', onClose);
        modal.open(dialog);
    });
}
