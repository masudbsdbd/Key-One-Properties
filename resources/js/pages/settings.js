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
