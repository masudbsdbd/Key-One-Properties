import $ from 'jquery';
import { http } from '../core/http';
import { clearErrors, showErrors, setPending } from '../components/form';

function bulkForm(target) {
    return target.closest('[data-bulk-form]');
}

function invalidate(form) {
    const submit = form.querySelector('[data-bulk-submit]');
    if (submit) submit.disabled = true;
    const preview = form.querySelector('[data-bulk-preview]');
    if (preview && preview.innerHTML) preview.classList.add('opacity-50');
}

async function preview(form, button) {
    clearErrors(form);
    setPending(button, true);
    try {
        const res = await http.post(form.dataset.previewUrl, new FormData(form), { silent: false });
        const box = form.querySelector('[data-bulk-preview]');
        box.classList.remove('opacity-50');
        box.innerHTML = res.data.html;
        form.querySelector('[data-bulk-submit]').disabled = box.querySelector('[data-bulk-ready]')?.value !== '1';
    } catch (error) {
        if (error.status === 422) showErrors(form, error.errors);
    } finally {
        setPending(button, false);
    }
}

async function selectAllFloors(form, button) {
    const building = form.querySelector('#bulk-building').value;
    if (!building) return;
    const res = await http.get(button.dataset.url, { building, page: 1 });
    const $select = $(form.querySelector('#bulk-floors'));
    res.results.forEach((item) => {
        if (!$select.find(`option[value="${item.id}"]`).length) $select.append(new Option(item.text, item.id, true, true));
    });
    $select.val(res.results.map((r) => r.id)).trigger('change');
}

export function initFlatPages() {
    document.addEventListener('click', (event) => {
        const previewButton = event.target.closest('[data-bulk-preview-button]');
        if (previewButton) {
            event.preventDefault();
            preview(bulkForm(previewButton), previewButton);
            return;
        }
        const allFloors = event.target.closest('[data-select-all-floors]');
        if (allFloors) {
            event.preventDefault();
            selectAllFloors(bulkForm(allFloors), allFloors);
        }
    });

    document.addEventListener('input', (event) => {
        const form = bulkForm(event.target);
        if (form) invalidate(form);
    });
    $(document).on('change', '[data-bulk-form] select', function () {
        invalidate(bulkForm(this));
    });
}
