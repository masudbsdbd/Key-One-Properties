import { http } from '../core/http';
import { confirmDialog } from './confirm';
import { toast } from './toast';
import { modal } from './modal';

/**
 * Buttons outside tables: [data-action="post|delete|put|patch"] with data-url,
 * optional data-confirm / data-confirm-text / data-tone.
 * On success: toast, close the enclosing modal, reload tables, fire "action:success".
 * data-reload reloads the page instead.
 */
export function initActions() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button || button.closest('[data-ajax-table]') || button.disabled) return;
        event.preventDefault();

        if (button.dataset.confirm) {
            const ok = await confirmDialog({
                title: button.dataset.confirm,
                message: button.dataset.confirmMessage || '',
                confirmText: button.dataset.confirmText || 'Confirm',
                tone: button.dataset.tone || 'danger',
            });
            if (!ok) return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        try {
            const method = button.dataset.action || 'post';
            const res = await http[method](button.dataset.url, {});
            if (res.message) toast.success(res.message);
            if (button.hasAttribute('data-reload')) {
                window.location.reload();
                return;
            }
            if (res.data?.redirect) {
                window.location.assign(res.data.redirect);
                return;
            }
            const dialog = button.closest('[data-modal]');
            if (dialog && !button.hasAttribute('data-keep-open')) modal.close(dialog);
            document.querySelectorAll('[data-ajax-table]').forEach((t) => t.reload?.());
            button.dispatchEvent(new CustomEvent('action:success', { bubbles: true, detail: res }));
        } catch {
            /* error toast already shown */
        } finally {
            button.disabled = false;
            button.setAttribute('aria-busy', 'false');
        }
    });
}
