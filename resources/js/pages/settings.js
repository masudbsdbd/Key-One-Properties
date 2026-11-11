import Sortable from 'sortablejs';
import { toast } from '../components/toast';
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

/** Default project stages and their work packages: add, remove and drag to reorder; numbers follow the order. */
export function initStageTemplates() {
    const form = document.querySelector('[data-stage-templates]');
    if (!form) return;
    const list = form.querySelector('[data-stage-template-list]');
    // Work packages are posted by stage position, so their names follow the order too.
    const renumber = () => list.querySelectorAll('[data-stage-template]').forEach((stage, i) => {
        stage.querySelector('[data-stage-number]').textContent = i + 1;
        stage.querySelectorAll('[data-package] input').forEach((input) => { input.name = `packages[${i}][]`; });
    });
    const addPackage = (adder) => {
        const input = adder.querySelector('[data-package-new]');
        const name = input.value.trim();
        if (!name) return;
        const taken = [...list.querySelectorAll('[data-package] input')].some((el) => el.value.toLowerCase() === name.toLowerCase());
        if (taken) {
            toast.error(`${name} is already in the list.`);
            return;
        }
        const chip = form.querySelector('[data-package-template]').content.firstElementChild.cloneNode(true);
        chip.querySelector('[data-package-name]').textContent = name;
        chip.querySelector('input').value = name;
        chip.querySelector('[data-package-remove]').setAttribute('aria-label', `Remove ${name}`);
        adder.before(chip);
        input.value = '';
        renumber();
    };
    Sortable.create(list, { handle: '[data-drag-handle]', animation: 150, onEnd: renumber });
    form.addEventListener('click', (event) => {
        if (event.target.closest('[data-stage-add]')) {
            list.append(form.querySelector('[data-stage-template-row]').content.cloneNode(true));
            renumber();
            list.lastElementChild.querySelector('input').focus();
            return;
        }
        const add = event.target.closest('[data-package-add]');
        if (add) {
            addPackage(add.closest('[data-package-adder]'));
            add.closest('[data-package-adder]').querySelector('input').focus();
            return;
        }
        const removePackage = event.target.closest('[data-package-remove]');
        if (removePackage) {
            removePackage.closest('[data-package]').remove();
            return;
        }
        const remove = event.target.closest('[data-stage-remove]');
        if (remove) {
            remove.closest('[data-stage-template]').remove();
            renumber();
        }
    });
    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || !event.target.matches('[data-package-new]')) return;
        event.preventDefault();
        addPackage(event.target.closest('[data-package-adder]'));
    });
    // A name typed but not added yet is kept when the form is saved.
    form.addEventListener('submit', () => form.querySelectorAll('[data-package-adder]').forEach(addPackage), true);
}
