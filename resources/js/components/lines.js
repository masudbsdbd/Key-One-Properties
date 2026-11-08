import $ from 'jquery';
import { http } from '../core/http';
import { initSelects } from './select';
import { Decimal } from '../pages/decimal';

function addRow(container, initial = null) {
    const template = container.querySelector('[data-line-template]');
    const index = (container._lineIndex = (container._lineIndex ?? -1) + 1);
    const wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML.replaceAll('__I__', index);
    const row = wrapper.firstElementChild;
    container.querySelector('[data-line-rows]').append(row);

    if (initial) {
        const select = row.querySelector('[data-line-material]');
        select.append(new Option(initial.text, initial.material, true, true));
        if (initial.quantity) row.querySelector('[data-line-qty]').value = initial.quantity;
        if (initial.unit_cost && row.querySelector('[data-line-cost]')) row.querySelector('[data-line-cost]').value = initial.unit_cost;
        if (initial.discount && row.querySelector('[data-line-discount]')) row.querySelector('[data-line-discount]').value = initial.discount;
        if (initial.tax && row.querySelector('[data-line-tax]')) row.querySelector('[data-line-tax]').value = initial.tax;
    }
    initSelects(row);
    if (initial) refreshAvailable(container, row);
    return row;
}

async function refreshAvailable(container, row) {
    const url = container.dataset.availableUrl;
    const target = row.querySelector('[data-line-available]');
    if (!url || !target) return;
    const warehouse = container.dataset.warehouse ? document.querySelector(container.dataset.warehouse)?.value : '';
    const material = row.querySelector('[data-line-material]').value;
    if (!warehouse || !material) {
        target.textContent = '';
        return;
    }
    try {
        const res = await http.get(url, { warehouse, material }, { silent: true });
        target.textContent = res.data.text;
    } catch {
        target.textContent = '';
    }
}

function updateTotal(container) {
    const out = container.querySelector('[data-lines-total]');
    if (!out) return;
    const num = (row, sel) => {
        const v = row.querySelector(sel)?.value.replace(/,/g, '') || '0';
        return Number.isNaN(Number(v)) ? '0' : v;
    };
    const products = [...container.querySelectorAll('[data-line]')].map((row) => {
        const gross = Decimal.product([num(row, '[data-line-qty]'), num(row, '[data-line-cost]')]);
        return Decimal.sum([gross, `-${num(row, '[data-line-discount]')}`.replace('--', ''), num(row, '[data-line-tax]')]);
    });
    out.textContent = `৳ ${Decimal.format(Decimal.sum(products))}`;
}

function setup(container) {
    if (container._linesReady) return;
    container._linesReady = true;

    let initial = [];
    try {
        initial = JSON.parse(container.querySelector('[data-line-initial]')?.textContent || '[]');
    } catch {
        initial = [];
    }
    if (initial.length) initial.forEach((line) => addRow(container, line));
    else addRow(container);

    container.addEventListener('click', (event) => {
        if (event.target.closest('[data-line-add]')) {
            addRow(container).querySelector('select')?.focus();
        }
        const remove = event.target.closest('[data-line-remove]');
        if (remove) {
            const rows = container.querySelectorAll('[data-line]');
            if (rows.length > 1) remove.closest('[data-line]').remove();
            updateTotal(container);
        }
    });
    container.addEventListener('input', () => updateTotal(container));
    $(container).on('change', '[data-line-material]', function () {
        refreshAvailable(container, this.closest('[data-line]'));
    });
    if (container.dataset.warehouse) {
        $(document).on('change', container.dataset.warehouse, () => container.querySelectorAll('[data-line]').forEach((row) => refreshAvailable(container, row)));
    }
}

export function initLineItems(root = document) {
    root.querySelectorAll('[data-lines]').forEach(setup);
}

export function bindLineItems() {
    initLineItems(document);
    document.addEventListener('remote:loaded', (event) => initLineItems(event.target));
}
