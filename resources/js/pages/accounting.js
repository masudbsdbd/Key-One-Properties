import $ from 'jquery';
import { initSelects } from '../components/select';
import { Decimal } from './decimal';

const clean = (value) => {
    const v = String(value || '').replace(/,/g, '').trim();
    return v === '' || Number.isNaN(Number(v)) ? '0' : v;
};

function totals(form) {
    const sum = (side) => Decimal.sum([...form.querySelectorAll(`[data-journal-amount="${side}"]`)].map((el) => clean(el.value)));
    const debit = sum('debit');
    const credit = sum('credit');
    form.querySelector('[data-journal-debit]').textContent = Decimal.format(debit);
    form.querySelector('[data-journal-credit]').textContent = Decimal.format(credit);
    const difference = Decimal.sum([debit, credit.startsWith('-') ? credit.slice(1) : `-${credit}`]);
    const balanced = Number(difference) === 0;
    form.querySelector('[data-journal-difference-row]').hidden = balanced;
    form.querySelector('[data-journal-difference]').textContent = Decimal.format(difference.replace('-', ''));
    const submit = form.querySelector('[data-journal-submit]');
    submit.disabled = !balanced || Number(debit) === 0;
    submit.dataset.disabledReason = balanced ? 'Enter the amounts first' : 'Debits and credits must be equal';
}

function addRow(form, line = null) {
    const index = (form._lineIndex = (form._lineIndex ?? -1) + 1);
    const holder = document.createElement('tbody');
    holder.innerHTML = form.querySelector('[data-journal-row]').innerHTML.replaceAll('__I__', index);
    const row = holder.firstElementChild;
    if (line) {
        if (line.account) row.querySelector('[name$="[account]"]').append(new Option(line.account.text, line.account.id, true, true));
        if (line.project) row.querySelector('[name$="[project]"]').append(new Option(line.project.text, line.project.id, true, true));
        row.querySelector('[name$="[description]"]').value = line.description || '';
        row.querySelector('[data-journal-amount="debit"]').value = line.debit || '';
        row.querySelector('[data-journal-amount="credit"]').value = line.credit || '';
    }
    form.querySelector('[data-journal-rows]').append(row);
    initSelects(row);
    return row;
}

/** Journal form: line rows, running totals and the difference between the two sides. */
export function initJournalForm() {
    const form = document.querySelector('[data-journal-form]');
    if (!form) return;
    const lines = JSON.parse(form.dataset.journalLines || '[]');
    (lines.length ? lines : [null, null]).forEach((line) => addRow(form, line));
    totals(form);

    form.addEventListener('click', (event) => {
        if (event.target.closest('[data-journal-add]')) {
            $(addRow(form).querySelector('select')).select2('open');
            return;
        }
        const remove = event.target.closest('[data-journal-remove]');
        if (remove) {
            remove.closest('[data-journal-line]').remove();
            if (!form.querySelector('[data-journal-line]')) addRow(form);
            totals(form);
            return;
        }
        const save = event.target.closest('[data-journal-save]');
        if (save) form.querySelector('[data-journal-then]').value = save.dataset.journalSave;
    });
    form.addEventListener('input', (event) => {
        const amount = event.target.closest('[data-journal-amount]');
        if (!amount) return;
        // A line holds one side: typing a debit clears the credit and the other way round.
        if (clean(amount.value) !== '0') {
            const other = amount.dataset.journalAmount === 'debit' ? 'credit' : 'debit';
            amount.closest('[data-journal-line]').querySelector(`[data-journal-amount="${other}"]`).value = '';
        }
        totals(form);
    });
}
