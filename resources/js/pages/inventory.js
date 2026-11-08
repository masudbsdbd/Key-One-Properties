import $ from 'jquery';
import { http } from '../core/http';
import { clearErrors, showErrors, setPending } from '../components/form';

function lockTransfer(form) {
    const submit = form.querySelector('[data-transfer-submit]');
    submit.disabled = true;
    submit.dataset.disabledReason = 'Preview the transfer first';
}

export function initInventoryPages() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-transfer-preview-button]');
        if (!button) return;
        event.preventDefault();
        const form = button.closest('[data-transfer-form]');
        clearErrors(form);
        setPending(button, true);
        try {
            const res = await http.post(form.dataset.previewUrl, new FormData(form));
            form.querySelector('[data-transfer-preview]').innerHTML = res.data.html;
            const submit = form.querySelector('[data-transfer-submit]');
            submit.disabled = !res.data.ok;
            submit.dataset.disabledReason = 'Not enough stock for some lines';
        } catch (error) {
            if (error.status === 422) showErrors(form, error.errors);
        } finally {
            setPending(button, false);
        }
    });

    document.addEventListener('input', (event) => {
        const form = event.target.closest('[data-transfer-form]');
        if (form) lockTransfer(form);
    });
    $(document).on('change', '[data-transfer-form] select', function () {
        lockTransfer(this.closest('[data-transfer-form]'));
    });

    document.addEventListener('ajax:success', (event) => {
        if (event.target.matches('[data-reload-on-success]')) window.location.reload();
    });
}
