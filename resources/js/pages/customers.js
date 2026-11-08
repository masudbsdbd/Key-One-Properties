import { http } from '../core/http';

export function initCustomerPages() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-reveal-identity]');
        if (!button) return;
        button.disabled = true;
        try {
            const res = await http.post(button.dataset.url, {});
            button.parentElement.querySelector('[data-identity-value]').textContent = res.data.value;
            button.remove();
        } catch {
            button.disabled = false;
        }
    });
}
