import $ from 'jquery';
import { http } from '../core/http';
import { clearErrors, showErrors, setPending } from '../components/form';

function bulkForm(target) {
    return target.closest('[data-bulk-form]');
}

function invalidate(form) {
    const preview = form.querySelector('[data-bulk-preview]');
    if (preview && preview.innerHTML) preview.classList.add('opacity-50');
}

function letters(index) {
    let out = '';
    for (let n = index; n > 0; n = Math.floor((n - 1) / 26)) out = String.fromCharCode(65 + ((n - 1) % 26)) + out;
    return out;
}

// Mirrors FlatService::flatNumber for the sample shown under the numbering field.
function unitNumber(pattern, floor, index) {
    const tokens = { '{floor}': String(floor), '{nn}': String(index).padStart(2, '0'), '{n}': String(index), '{L}': letters(index) };
    return pattern.replace(/\{floor\}|\{nn\}|\{n\}|\{L\}/g, (token) => tokens[token]).toUpperCase();
}

function showExample(form) {
    const example = form.querySelector('[data-bulk-example]');
    if (!example) return;
    const style = form.querySelector('#bulk-numbering').value;
    const custom = style === 'custom';
    form.querySelector('[data-bulk-custom]').hidden = !custom;
    const pattern = custom ? form.querySelector('#bulk-pattern').value : JSON.parse(form.dataset.patterns)[style];
    const count = Math.min(Math.max(parseInt(form.querySelector('[name="per_floor"]').value, 10) || 4, 1), 50);
    const start = Math.max(parseInt(form.querySelector('[name="start_index"]').value, 10) || 1, 1);
    if (!pattern) {
        example.textContent = '';
        return;
    }
    const shown = Math.min(count, 4);
    const row = (floor) => {
        const numbers = Array.from({ length: shown }, (_, i) => unitNumber(pattern, floor, start + i));
        if (count > shown) numbers.push(`… ${unitNumber(pattern, floor, start + count - 1)}`);
        return numbers.join(', ');
    };
    example.textContent = `Floor 1: ${row(1)} · Floor 2: ${row(2)}`;
}

async function preview(form, button) {
    clearErrors(form);
    setPending(button, true);
    try {
        const res = await http.post(form.dataset.previewUrl, new FormData(form), { silent: false });
        const box = form.querySelector('[data-bulk-preview]');
        box.classList.remove('opacity-50');
        box.innerHTML = res.data.html;
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
        if (!form) return;
        invalidate(form);
        showExample(form);
    });
    $(document).on('change', '[data-bulk-form] select', function () {
        invalidate(bulkForm(this));
        showExample(bulkForm(this));
    });
    document.addEventListener('remote:loaded', (event) => {
        const form = event.target.querySelector('[data-bulk-form]');
        if (form) showExample(form);
    });
}
