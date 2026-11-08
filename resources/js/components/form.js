import $ from 'jquery';
import { http } from '../core/http';
import { toast } from './toast';
import { modal } from './modal';

export function clearErrors(form) {
    form.querySelectorAll('[aria-invalid="true"]').forEach((el) => el.removeAttribute('aria-invalid'));
    form.querySelectorAll('[data-error]').forEach((el) => {
        el.textContent = '';
        el.hidden = true;
    });
    form.querySelector('[data-form-error]')?.setAttribute('hidden', '');
}

export function showErrors(form, errors) {
    let first = null;
    Object.entries(errors).forEach(([name, list]) => {
        const field = fieldFor(form, name);
        const message = Array.isArray(list) ? list[0] : list;
        if (!field) {
            const box = form.querySelector('[data-form-error]');
            if (box) {
                box.textContent = message;
                box.hidden = false;
            }
            return;
        }
        field.setAttribute('aria-invalid', 'true');
        const holder = field.closest('[data-field]')?.querySelector('[data-error]');
        if (holder) {
            holder.textContent = message;
            holder.hidden = false;
        }
        first ??= field;
    });
    first?.focus();
}

function fieldFor(form, name) {
    const bracket = name.replace(/\.(\w+)/g, '[$1]');
    return (
        form.querySelector(`[name="${name}"]`) ||
        form.querySelector(`[name="${bracket}"]`) ||
        form.querySelector(`[name="${bracket}[]"]`) ||
        form.querySelector(`[name="${name}[]"]`)
    );
}

export function setPending(button, pending) {
    if (!button) return;
    button.disabled = pending;
    button.setAttribute('aria-busy', pending ? 'true' : 'false');
}

/**
 * <form data-ajax-form> submits via AJAX.
 *  - Disables the submit button and blocks double submission.
 *  - Renders 422 errors inline next to fields.
 *  - On success: toast, close parent modal, and dispatch "ajax:success"
 *    (detail = response body). data-reset resets the form on success,
 *    data-redirect follows body.data.redirect when present.
 */
export function initAjaxForms(root = document) {
    $(root).on('submit', 'form[data-ajax-form]', async function (event) {
        event.preventDefault();
        const form = this;
        if (form.dataset.pending === 'true') return;

        const submit = form.querySelector('[type="submit"]');
        form.dataset.pending = 'true';
        setPending(submit, true);
        clearErrors(form);

        try {
            const body = await http.request({
                method: 'POST',
                url: form.action,
                data: new FormData(form),
            });
            if (body.message) toast.success(body.message);
            if (body.data?.redirect && form.dataset.redirect !== 'false') {
                window.location.assign(body.data.redirect);
                return;
            }
            if (form.hasAttribute('data-reset')) {
                form.reset();
                $(form).find('select[data-select]').val(null).trigger('change');
            }
            const dialog = form.closest('[data-modal]');
            if (dialog && form.dataset.keepOpen !== 'true') modal.close(dialog);
            form.dispatchEvent(new CustomEvent('ajax:success', { bubbles: true, detail: body }));
        } catch (error) {
            if (error.status === 422) {
                showErrors(form, error.errors);
            }
            form.dispatchEvent(new CustomEvent('ajax:error', { bubbles: true, detail: error }));
        } finally {
            form.dataset.pending = 'false';
            setPending(submit, false);
        }
    });

    $(root).on('input change', 'form[data-ajax-form] [aria-invalid="true"]', function () {
        this.removeAttribute('aria-invalid');
        const holder = this.closest('[data-field]')?.querySelector('[data-error]');
        if (holder) holder.hidden = true;
    });
}
