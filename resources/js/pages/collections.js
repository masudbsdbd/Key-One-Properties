import $ from 'jquery';
import { http } from '../core/http';
import { clearErrors, showErrors, setPending } from '../components/form';

let previewTimer;

function togglePlanMode(form) {
    const custom = form.querySelector('[data-plan-frequency]').value === 'custom';
    form.querySelector('[data-plan-custom]').hidden = !custom;
    form.querySelectorAll('[data-plan-regular]').forEach((el) => {
        el.closest('[data-field]').hidden = custom;
    });
}

export function initCollectionPages() {
    $(document).on('change', '[data-plan-form] [data-plan-frequency]', function () {
        togglePlanMode(this.closest('[data-plan-form]'));
    });
    document.addEventListener('remote:loaded', (event) => {
        const form = event.target.querySelector('[data-plan-form]');
        if (form) togglePlanMode(form);
    });

    document.addEventListener('click', async (event) => {
        const add = event.target.closest('[data-plan-add-line]');
        if (add) {
            const box = add.closest('[data-plan-form]').querySelector('[data-plan-lines]');
            const i = box.children.length;
            const row = box.lastElementChild.cloneNode(true);
            row.querySelectorAll('input').forEach((input) => {
                input.value = '';
                input.name = input.name.replace(/lines\[\d+\]/, `lines[${i}]`);
            });
            box.append(row);
            return;
        }

        const button = event.target.closest('[data-plan-preview]');
        if (!button) return;
        event.preventDefault();
        const form = button.closest('[data-plan-form]');
        clearErrors(form);
        setPending(button, true);
        try {
            const res = await http.post(form.dataset.previewUrl, new FormData(form), { silent: true });
            form.querySelector('[data-plan-output]').innerHTML = res.data.html;
        } catch (error) {
            if (error.status === 422) showErrors(form, error.errors);
        } finally {
            setPending(button, false);
        }
    });

    document.addEventListener('input', (event) => {
        if (!event.target.matches('[data-payment-amount]')) return;
        const form = event.target.closest('[data-payment-preview]');
        if (!form || !form.dataset.paymentPreview) return;
        clearTimeout(previewTimer);
        previewTimer = setTimeout(async () => {
            try {
                const res = await http.get(form.dataset.paymentPreview, { amount: event.target.value }, { silent: true });
                form.querySelector('[data-payment-allocation]').innerHTML = res.data.html;
            } catch {
                /* keep the previous preview */
            }
        }, 300);
    });
}
