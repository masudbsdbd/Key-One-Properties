function sync(form) {
    form.querySelectorAll('[data-permission-group]').forEach((group) => {
        const boxes = [...group.querySelectorAll('input[name="permissions[]"]:not(:disabled)')];
        const toggle = group.querySelector('[data-check-group]');
        const checked = boxes.filter((b) => b.checked).length;
        toggle.checked = boxes.length > 0 && checked === boxes.length;
        toggle.indeterminate = checked > 0 && checked < boxes.length;
        toggle.disabled = boxes.length === 0;
    });
    const all = [...form.querySelectorAll('input[name="permissions[]"]:not(:disabled)')];
    const master = form.querySelector('[data-check-all]');
    const count = all.filter((b) => b.checked).length;
    master.checked = all.length > 0 && count === all.length;
    master.indeterminate = count > 0 && count < all.length;
}

export function initPermissionForms() {
    document.addEventListener('change', (event) => {
        const form = event.target.closest('[data-permission-form]');
        if (!form) return;
        if (event.target.matches('[data-check-group]')) {
            event.target.closest('[data-permission-group]').querySelectorAll('input[name="permissions[]"]:not(:disabled)').forEach((b) => (b.checked = event.target.checked));
        }
        if (event.target.matches('[data-check-all]')) {
            form.querySelectorAll('input[name="permissions[]"]:not(:disabled)').forEach((b) => (b.checked = event.target.checked));
        }
        sync(form);
    });
    document.addEventListener('remote:loaded', (event) => {
        const form = event.target.querySelector('[data-permission-form]');
        if (form) sync(form);
    });
}
