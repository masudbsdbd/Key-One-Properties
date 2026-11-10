import $ from 'jquery';
import { Decimal } from './decimal';

/** Choosing a labour type fills its default daily rate unless the rate was typed by hand. */
function fillLabourRate(select) {
    const form = select.closest('[data-labour-form]');
    const rate = JSON.parse(form?.dataset.labourRates || '{}')[select.value];
    const input = select.closest('[data-labour-row]')?.querySelector('[name$="[rate]"]');
    if (!input || !rate || (input.value && input.dataset.autoRate !== input.value)) return;
    input.value = rate;
    input.dataset.autoRate = rate;
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

const number = (v) => {
    const s = String(v ?? '').replace(/,/g, '').trim();
    return s !== '' && !Number.isNaN(Number(s)) ? s : null;
};

function labourTotals(form) {
    const amounts = [...form.querySelectorAll('[data-labour-row]')].map((row) => {
        const values = [...row.querySelectorAll('[data-labour-qty]')].map((i) => number(i.value));
        const amount = values.every((v) => v !== null) ? Decimal.product(values) : '0';
        row.querySelector('[data-labour-amount]').textContent = `৳ ${Decimal.format(amount)}`;
        return amount;
    });
    form.querySelector('[data-calc-total]').textContent = `৳ ${Decimal.format(Decimal.sum(amounts))}`;
    const rows = form.querySelectorAll('[data-labour-row]');
    form.querySelector('[data-labour-count]').textContent = rows.length > 1 ? `${rows.length} rows ·` : '';
    rows.forEach((row) => { row.querySelector('[data-labour-remove]').disabled = rows.length === 1; });
}

function addLabourRow(form) {
    form._labourIndex = (form._labourIndex ?? -1) + 1;
    const html = form.querySelector('[data-labour-template]').innerHTML.replaceAll('__I__', form._labourIndex);
    form.querySelector('[data-labour-rows]').insertAdjacentHTML('beforeend', html);
    labourTotals(form);
    form.querySelector('[data-labour-row]:last-child select')?.focus();
}

/**
 * Live totals: the labour sheet sums rows of headcount × days × rate; [data-calc-form] multiplies its [data-calc] inputs.
 * The server recalculates and stores the authoritative amounts.
 */
export function initCalculators() {
    $(document).on('change', '[data-labour-form] select[name$="[type]"]', function () {
        fillLabourRate(this);
    });
    document.addEventListener('remote:loaded', (event) => {
        event.target.querySelectorAll?.('[data-labour-form]').forEach((form) => { if (!form.querySelector('[data-labour-row]')) addLabourRow(form); });
    });
    document.addEventListener('click', (event) => {
        const form = event.target.closest('[data-labour-form]');
        if (!form) return;
        if (event.target.closest('[data-labour-add]')) addLabourRow(form);
        const remove = event.target.closest('[data-labour-remove]');
        if (remove && !remove.disabled) {
            remove.closest('[data-labour-row]').remove();
            labourTotals(form);
        }
    });
    document.addEventListener('input', (event) => {
        const labour = event.target.closest('[data-labour-form]');
        if (labour && event.target.matches('[data-labour-qty]')) labourTotals(labour);
        const form = event.target.closest('[data-calc-form]');
        if (!form || !event.target.matches('[data-calc]')) return;
        const values = [...form.querySelectorAll('[data-calc]')].map((i) => i.value.replace(/,/g, ''));
        const total = values.every((v) => v !== '' && !Number.isNaN(Number(v))) ? Decimal.product(values) : '0.00';
        form.querySelector('[data-calc-total]').textContent = `৳ ${Decimal.format(total)}`;
    });
}
