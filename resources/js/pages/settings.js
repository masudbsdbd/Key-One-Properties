import Sortable from 'sortablejs';
const HEX = /^#[0-9a-f]{6}$/i;

export function initSettingsPage() {
    const form = document.querySelector('[data-branding-form]');
    if (!form) return;
    const root = document.documentElement;

    const apply = (field, value) => {
        if (!HEX.test(value)) return;
        const picker = form.querySelector(`[data-color-picker="${field}"]`);
        const text = form.querySelector(`[data-color-text="${field}"]`);
        if (picker.value.toLowerCase() !== value.toLowerCase()) picker.value = value;
        if (text.value.toLowerCase() !== value.toLowerCase()) text.value = value;
        root.style.setProperty(picker.dataset.cssVar, value);
    };

    form.addEventListener('input', (event) => {
        const field = event.target.dataset.colorPicker || event.target.dataset.colorText;
        if (field) apply(field, event.target.value.trim());
    });

    form.querySelector('[data-reset-colors]')?.addEventListener('click', (event) => {
        apply('primary_color', event.currentTarget.dataset.primary);
        apply('sidebar_color', event.currentTarget.dataset.sidebar);
    });
}

/** Default project stages: add, remove and drag to reorder; numbers follow the order. */
export function initStageTemplates() {
    const form = document.querySelector('[data-stage-templates]');
    if (!form) return;
    const list = form.querySelector('[data-stage-template-list]');
    const renumber = () => list.querySelectorAll('[data-stage-number]').forEach((el, i) => { el.textContent = i + 1; });
    Sortable.create(list, { handle: '[data-drag-handle]', animation: 150, onEnd: renumber });
    form.addEventListener('click', (event) => {
        if (event.target.closest('[data-stage-add]')) {
            list.append(form.querySelector('[data-stage-template-row]').content.cloneNode(true));
            renumber();
            list.lastElementChild.querySelector('input').focus();
            return;
        }
        const remove = event.target.closest('[data-stage-remove]');
        if (remove) {
            remove.closest('[data-stage-template]').remove();
            renumber();
        }
    });
}
