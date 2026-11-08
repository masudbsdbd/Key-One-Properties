import { Decimal } from './decimal';

const clean = (v) => {
    const s = String(v ?? '').replace(/,/g, '').trim();
    return /^\d*\.?\d*$/.test(s) && s !== '' && s !== '.' ? s : '0';
};
const negate = (v) => (v.startsWith('-') ? v.slice(1) : `-${v}`);

function billTotals(form) {
    const gross = Decimal.sum([...form.querySelectorAll('[data-cbill-line]:checked')].map((el) => el.dataset.amount));
    const retention = form.querySelector('[data-cbill-retention]');
    if (retention && !retention.dataset.touched) {
        const pct = form.dataset.retention || '0';
        retention.value = Decimal.product([gross, pct, '0.01']);
    }
    const deductions = [...form.querySelectorAll('[data-cbill-deduct]')].filter((el) => !el.disabled).map((el) => negate(clean(el.value)));
    const net = Decimal.sum([gross, ...deductions]);
    form.querySelector('[data-cbill-gross]').textContent = `৳ ${Decimal.format(gross)}`;
    const out = form.querySelector('[data-cbill-net]');
    out.textContent = `৳ ${Decimal.format(net)}`;
    out.classList.toggle('text-danger-600', net.startsWith('-'));
}

export function initContractorPages() {
    document.addEventListener('input', (event) => {
        const form = event.target.closest('[data-cbill-form]');
        if (!form) return;
        if (event.target.matches('[data-cbill-retention]')) event.target.dataset.touched = '1';
        billTotals(form);
    });
    document.addEventListener('change', (event) => {
        const form = event.target.closest('[data-cbill-form]');
        if (!form) return;
        if (event.target.matches('[data-cbill-all]')) {
            form.querySelectorAll('[data-cbill-line]').forEach((el) => { el.checked = event.target.checked; });
        }
        billTotals(form);
    });
    document.addEventListener('remote:loaded', (event) => {
        const form = event.target.querySelector('[data-cbill-form]');
        if (form) billTotals(form);
    });
}
