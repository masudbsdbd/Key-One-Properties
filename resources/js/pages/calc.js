import $ from 'jquery';
import { Decimal } from './decimal';

/**
 * Live total for forms with [data-calc] inputs: product of all inputs, shown in [data-calc-total].
 * The server recalculates and stores the authoritative amount.
 */
/** Choosing a labour type fills its default daily rate unless the rate was typed by hand. */
function fillLabourRate(select) {
    const form = select.closest('[data-labour-form]');
    const rate = JSON.parse(form?.dataset.labourRates || '{}')[select.value];
    const input = select.closest('[data-labour-row]')?.querySelector('[name$="[rate]"]') || form?.querySelector('[name="rate"]');
    if (!input || !rate || (input.value && input.dataset.autoRate !== input.value)) return;
    input.value = rate;
    input.dataset.autoRate = rate;
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

export function initCalculators() {
    $(document).on('change', '[data-labour-form] select[name$="labour_type"], [data-labour-form] select[name$="[type]"]', function () {
        fillLabourRate(this);
    });
    document.addEventListener('input', (event) => {
        const form = event.target.closest('[data-labour-form], [data-calc-form]');
        if (!form || !event.target.matches('[data-calc]')) return;
        const values = [...form.querySelectorAll('[data-calc]')].map((i) => i.value.replace(/,/g, ''));
        const total = values.every((v) => v !== '' && !Number.isNaN(Number(v))) ? Decimal.product(values) : '0.00';
        form.querySelector('[data-calc-total]').textContent = `৳ ${Decimal.format(total)}`;
    });
}
