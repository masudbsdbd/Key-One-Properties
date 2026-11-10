import $ from 'jquery';
import { http } from '../core/http';

function format(value) {
    return `${Number(value.toFixed(4))}%`;
}

function updateGauge(form) {
    const gauge = form.querySelector('[data-share-gauge]');
    if (!gauge) return;
    const taken = parseFloat(gauge.dataset.allocated) || 0;
    const share = Math.max(0, parseFloat(String(form.querySelector('[data-share-input]').value).replace(/[,%]/g, '')) || 0);
    const free = 100 - taken - share;
    const bar = gauge.querySelector('[data-gauge-this]');
    bar.style.width = `${Math.min(share, Math.max(0, 100 - taken))}%`;
    bar.classList.toggle('bg-danger-600', free < 0);
    bar.classList.toggle('bg-accent-500', free >= 0);
    gauge.querySelector('[data-gauge-this-label]').textContent = format(share);
    gauge.querySelector('[data-gauge-total]').textContent = format(taken + share);
    const freeLabel = gauge.querySelector('[data-gauge-free]');
    freeLabel.textContent = free < 0 ? `Over by ${format(-free)}` : format(free);
    freeLabel.classList.toggle('text-danger-600', free < 0);
}

async function loadGauge(form) {
    const host = form.querySelector('[data-share-gauge-host]');
    const flat = form.querySelector('[data-agreement-flat]')?.value;
    if (!flat) {
        host.innerHTML = '';
        return;
    }
    try {
        const res = await http.get(form.dataset.sharesUrl, {
            flat,
            from: form.querySelector('[name=effective_from]').value,
            to: form.querySelector('[name=effective_to]').value,
            agreement: form.dataset.agreement || '',
        }, { silent: true });
        host.innerHTML = res.data.html;
        updateGauge(form);
    } catch {
        /* keep the previous gauge */
    }
}

export function initInvestorPages() {
    document.addEventListener('change', (event) => {
        if (!event.target.matches('[data-capital-form] [name="transaction_type"]')) return;
        const title = event.target.closest('[data-modal]')?.querySelector('[data-remote-title]');
        if (title) title.textContent = event.target.value === 'capital_return' ? 'Return Capital' : 'Receive Capital';
    });
    document.addEventListener('remote:loaded', (event) => {
        const form = event.target.querySelector('[data-agreement-form]');
        if (form) loadGauge(form);
    });
    // Select2 fires jQuery events only; jQuery also sees native ones.
    $(document).on('change', '[data-agreement-form] [data-agreement-flat], [data-agreement-form] [data-share-date]', function () {
        loadGauge(this.closest('[data-agreement-form]'));
    });
    document.addEventListener('input', (event) => {
        const form = event.target.closest('[data-agreement-form]');
        if (!form) return;
        if (event.target.matches('[data-share-input]')) updateGauge(form);
    });
}
