import { Decimal } from './decimal';

/**
 * Live total for forms with [data-calc] inputs: product of all inputs, shown in [data-calc-total].
 * The server recalculates and stores the authoritative amount.
 */
export function initCalculators() {
    document.addEventListener('input', (event) => {
        const form = event.target.closest('[data-labour-form]');
        if (!form || !event.target.matches('[data-calc]')) return;
        const values = [...form.querySelectorAll('[data-calc]')].map((i) => i.value.replace(/,/g, ''));
        const total = values.every((v) => v !== '' && !Number.isNaN(Number(v))) ? Decimal.product(values) : '0.00';
        form.querySelector('[data-calc-total]').textContent = `৳ ${Decimal.format(total)}`;
    });
}
