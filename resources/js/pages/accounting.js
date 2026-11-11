import $ from 'jquery';
import { http } from '../core/http';
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

function syncMove(form) {
    const other = form.querySelector('[data-move-other]').value;
    const own = form.querySelector('#move-account').value;
    const show = { account: other.startsWith('account:'), purpose: other.startsWith('purpose:'), other: other === 'purpose:other' };
    form.querySelectorAll('[data-move-when]').forEach((el) => { el.hidden = !show[el.dataset.moveWhen]; });
    // The account on the other side cannot be the same one.
    form.querySelectorAll('[data-move-other] option').forEach((option) => { option.disabled = own !== '' && option.value === `account:${own}`; });
}

/** Deposit, withdraw and transfer form: fields follow where the money comes from or goes. */
export function initMoneyMoveForm() {
    document.addEventListener('remote:loaded', (event) => {
        const form = event.target.querySelector?.('[data-move-form]');
        if (form) syncMove(form);
    });
    $(document).on('change', '[data-move-form] select', function () {
        const form = this.closest('[data-move-form]');
        syncMove(form);
        if (this.id === 'move-account') $(form.querySelector('[data-move-other]')).trigger('change.select2');
    });
}

/** Opening balances: totals per side and the amount that lands in Opening Balance Equity. */
export function initOpeningForm() {
    const form = document.querySelector('[data-opening-form]');
    if (!form) return;
    const update = () => {
        const sum = (side) => Decimal.sum([...form.querySelectorAll(`[data-opening-amount="${side}"]`)].map((el) => clean(el.value)));
        const asset = sum('asset');
        const other = sum('other');
        form.querySelector('[data-opening-total="asset"]').textContent = Decimal.format(asset);
        form.querySelector('[data-opening-total="other"]').textContent = Decimal.format(other);
        form.querySelector('[data-opening-total="difference"]').textContent = Decimal.format(Decimal.sum([asset, other.startsWith('-') ? other.slice(1) : `-${other}`]));
    };
    form.addEventListener('input', update);
    update();
}

/** Customer payment form: cheque details appear when the method is cheque; a cheque still in hand needs no bank account. */
export function initChequeFields() {
    const sync = (form) => {
        const cheque = form.querySelector('[name="method"]')?.value === 'cheque';
        const fields = form.querySelector('[data-cheque-fields]');
        if (!fields) return;
        fields.hidden = !cheque;
        const held = cheque && ['in_hand', 'bounced'].includes(form.querySelector('[name="cheque_status"]').value);
        const account = form.querySelector('[name="money_account"]');
        const field = account?.closest('[data-field]');
        if (field) field.hidden = held;
        if (account && held) $(account).val(null).trigger('change.select2');
    };
    document.addEventListener('remote:loaded', (event) => {
        const form = event.target.querySelector?.('[data-collect-form]');
        if (form) sync(form);
    });
    $(document).on('change', '[data-collect-form] [name="method"], [data-collect-form] [name="cheque_status"]', function () {
        sync(this.closest('[data-collect-form]'));
    });
}

/** Financial statements: a filter change or a link inside the report loads only the report body. */
export function initReportFilters() {
    const form = document.querySelector('[data-report-filters]');
    const body = document.querySelector('[data-report-body]');
    if (!form || !body) return;

    const load = async (url) => {
        const target = new URL(url, window.location.origin);
        window.history.replaceState({}, '', target);
        target.searchParams.set('partial', '1');
        body.setAttribute('aria-busy', 'true');
        body.classList.add('opacity-60');
        try {
            const res = await http.get(target.toString(), {}, { silent: true });
            body.innerHTML = res.data.html;
        } finally {
            body.removeAttribute('aria-busy');
            body.classList.remove('opacity-60');
        }
    };
    const current = () => {
        const custom = form.querySelector('[name="period"]')?.value === 'custom';
        const params = new URLSearchParams([...new FormData(form)].filter(([name, value]) => value !== '' && (custom || !['from', 'to'].includes(name))));
        return `${form.action}?${params}`;
    };

    $(form).on('change', 'select, input', (event) => {
        const period = form.querySelector('[data-report-period]');
        const dates = form.querySelector('[data-report-dates]');
        if (dates && period) dates.hidden = period.value !== 'custom';
        // Choosing "Custom Dates" only reveals the date fields; the report reloads once a date is set.
        if (event.target === period && period.value === 'custom') return;
        load(current());
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        load(current());
    });
    body.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-report-link]');
        if (!link) return;
        event.preventDefault();
        const url = new URL(link.href);
        // A link that changes a filter (project drill-down) is mirrored in the form.
        const project = form.querySelector('[name="project"]');
        if (project && url.searchParams.has('project') && project.value !== url.searchParams.get('project')) {
            $(project).val(url.searchParams.get('project')).trigger('change.select2');
        }
        load(url.toString()).then(() => body.scrollIntoView({ block: 'nearest' }));
    });
}

/** Bank reconciliation: ticking an entry saves at once and refreshes the totals. */
export function initBankReconciliation() {
    const card = document.querySelector('[data-recon]');
    const summary = document.querySelector('[data-recon-summary]');
    if (!card || !summary) return;
    const save = async (boxes, ticked) => {
        boxes.forEach((box) => { box.disabled = true; });
        try {
            const res = await http.post(card.dataset.url, { lines: boxes.map((box) => box.value), ticked: ticked ? 1 : 0 });
            summary.innerHTML = res.data.html;
        } catch {
            boxes.forEach((box) => { box.checked = !ticked; });
        } finally {
            boxes.forEach((box) => { box.disabled = false; });
        }
    };
    card.addEventListener('change', (event) => {
        const box = event.target.closest('[data-recon-line]');
        if (box) save([box], box.checked);
    });
    card.addEventListener('click', (event) => {
        if (!event.target.closest('[data-recon-all]')) return;
        const open = [...card.querySelectorAll('[data-recon-line]:not(:checked)')];
        if (!open.length) return;
        open.forEach((box) => { box.checked = true; });
        save(open, true);
    });
}

/**
 * Payment and receipt forms: when only one account fits the chosen method (one cash box, one bKash wallet),
 * it is filled in, so the user does not pick from a list of one.
 */
export function initSingleAccountPick() {
    const pick = async (select) => {
        if (select.value || select.closest('[data-field]')?.hidden) return;
        const parent = document.querySelector(select.dataset.dependsOn || '');
        const method = parent ? parent.value : '';
        if (parent && !method) return;
        try {
            const res = await http.get(select.dataset.selectUrl, { method, page: 1 }, { silent: true });
            const stillSame = !parent || parent.value === method;
            if (res.results?.length === 1 && !select.value && stillSame) {
                select.append(new Option(res.results[0].text, res.results[0].id, true, true));
                $(select).trigger('change');
            }
        } catch {
            /* the user can still choose by hand */
        }
    };
    const all = (root) => root.querySelectorAll?.('select[name="money_account"][data-select-url]') || [];
    document.addEventListener('remote:loaded', (event) => all(event.target).forEach(pick));
    $(document).on('change', 'select', function () {
        if (!this.id) return;
        // The dependent select empties itself first; fill it afterwards.
        setTimeout(() => document.querySelectorAll(`select[name="money_account"][data-depends-on="#${CSS.escape(this.id)}"]`).forEach(pick), 0);
    });
    all(document).forEach(pick);
}

/** New account form: bank name and branch only matter for a bank account. */
export function initMoneyAccountForm() {
    const sync = (form) => {
        const bank = form.querySelector('[name="kind"]')?.value === 'bank';
        form.querySelectorAll('[data-bank-only]').forEach((el) => { el.hidden = !bank; });
    };
    document.addEventListener('remote:loaded', (event) => {
        const form = event.target.querySelector?.('[data-money-account-form]');
        if (form) sync(form);
    });
    $(document).on('change', '[data-money-account-form] [name="kind"]', function () {
        sync(this.closest('[data-money-account-form]'));
    });
}
