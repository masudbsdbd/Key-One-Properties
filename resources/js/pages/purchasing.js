import $ from 'jquery';
import { Decimal } from './decimal';

const clean = (v) => {
    const s = String(v ?? '').replace(/,/g, '').trim();
    return /^-?\d*\.?\d*$/.test(s) && s !== '' && s !== '.' && s !== '-' ? s : '0';
};

function billTotal(form) {
    const lines = [...form.querySelectorAll('[data-bill-line]:checked')].map((el) => el.dataset.amount);
    const extras = [...form.querySelectorAll('[data-bill-amount]')].map((el) => clean(el.value));
    form.querySelector('[data-bill-total]').textContent = `৳ ${Decimal.format(Decimal.sum([...lines, ...extras]))}`;
}

function unallocated(form) {
    const amount = clean(form.querySelector('[data-payment-amount]')?.value);
    const allocated = [...form.querySelectorAll('[data-allocation]')].map((el) => clean(el.value));
    const rest = Decimal.sum([amount, ...allocated.map((v) => (v.startsWith('-') ? v.slice(1) : `-${v}`))]);
    const out = form.querySelector('[data-payment-unallocated]');
    out.textContent = `৳ ${Decimal.format(rest)}`;
    out.classList.toggle('text-danger-600', rest.startsWith('-'));
}

export function initPurchasingPages() {
    document.addEventListener('input', (event) => {
        const bill = event.target.closest('[data-bill-form]');
        if (bill) billTotal(bill);
        const payment = event.target.closest('[data-payment-form]');
        if (payment) unallocated(payment);
    });
    document.addEventListener('change', (event) => {
        const bill = event.target.closest('[data-bill-form]');
        if (!bill) return;
        if (event.target.matches('[data-bill-all]')) {
            bill.querySelectorAll('[data-bill-line]').forEach((el) => { el.checked = event.target.checked; });
        }
        billTotal(bill);
    });
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-allocate-full]');
        if (!button) return;
        const form = button.closest('[data-payment-form]');
        const input = button.parentElement.querySelector('[data-allocation]');
        input.value = input.dataset.max;
        const amount = form.querySelector('[data-payment-amount]');
        if (clean(amount.value) === '0') {
            amount.value = Decimal.sum([...form.querySelectorAll('[data-allocation]')].map((el) => clean(el.value)));
        }
        unallocated(form);
    });
    document.addEventListener('partial:loaded', (event) => {
        const bill = event.target.closest('[data-bill-form]');
        if (bill) billTotal(bill);
        const payment = event.target.closest('[data-payment-form]');
        if (payment) unallocated(payment);
    });
}

/** Expense form: project fields only for project scope. */
export function initExpenseForms() {
    $(document).on('change', '[data-expense-form] #exp-scope', function () {
        const project = this.closest('[data-expense-form]').querySelector('[data-expense-project]');
        project.hidden = this.value !== 'project';
    });
}
