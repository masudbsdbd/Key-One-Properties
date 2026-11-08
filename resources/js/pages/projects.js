import { http } from '../core/http';
import { toast } from '../components/toast';
import { reloadPartials } from '../components/partials';

function initViewToggle() {
    const table = document.querySelector('[data-project-table]');
    if (!table) return;
    const input = table.querySelector('[data-view-input]');
    const thead = table.querySelector('thead');

    let saved = 'table';
    try {
        saved = localStorage.getItem('keyone.projects.view') || 'table';
    } catch {
        /* storage unavailable */
    }

    const apply = (view, reload) => {
        input.value = view;
        thead.hidden = view === 'cards';
        table.querySelectorAll('[data-view-toggle]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.viewToggle === view)));
        try {
            localStorage.setItem('keyone.projects.view', view);
        } catch {
            /* storage unavailable */
        }
        if (reload) table.reload?.();
    };

    apply(saved, false);
    table.addEventListener('click', (event) => {
        const button = event.target.closest('[data-view-toggle]');
        if (button) apply(button.dataset.viewToggle, true);
    });
}

function initStageReorder() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-stage-move]');
        if (!button) return;
        const list = button.closest('[data-stage-list]');
        const item = button.closest('[data-stage-id]');
        const sibling = button.dataset.stageMove === 'up' ? item.previousElementSibling : item.nextElementSibling;
        if (!sibling) return;

        if (button.dataset.stageMove === 'up') sibling.before(item);
        else sibling.after(item);

        const order = [...list.querySelectorAll('[data-stage-id]')].map((el) => el.dataset.stageId);
        list.querySelectorAll('[data-stage-move]').forEach((b) => (b.disabled = true));
        try {
            await http.post(list.dataset.reorderUrl, { order });
            toast.success('Stage order saved.');
        } finally {
            reloadPartials();
        }
    });
}

function initAutoSubmit() {
    document.addEventListener('change', (event) => {
        if (event.target.matches('input[type="file"][data-auto-submit]') && event.target.files.length) {
            event.target.form.requestSubmit();
        }
    });
}

export function initProjectPages() {
    initViewToggle();
    initStageReorder();
    initAutoSubmit();
}
