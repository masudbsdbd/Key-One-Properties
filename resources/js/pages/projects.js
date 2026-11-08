import Sortable from 'sortablejs';
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

async function saveOrder(list) {
    const order = [...list.querySelectorAll('[data-stage-id]')].map((el) => el.dataset.stageId);
    const container = list.closest('[data-reorder-url]');
    list.classList.add('pointer-events-none', 'opacity-70');
    try {
        await http.post(container.dataset.reorderUrl, { order });
        toast.success('Stage order saved.');
    } finally {
        reloadPartials();
    }
}

function initStageReorder() {
    const bind = () => {
        document.querySelectorAll('[data-sortable-stages]').forEach((list) => {
            if (list._sortable) return;
            list._sortable = Sortable.create(list, {
                handle: '[data-drag-handle]',
                forceFallback: true,
                fallbackOnBody: true,
                animation: 150,
                ghostClass: 'opacity-40',
                chosenClass: 'shadow-raised',
                onEnd: (event) => {
                    if (event.oldIndex !== event.newIndex) saveOrder(list);
                },
            });
        });
    };

    bind();
    document.addEventListener('partial:loaded', bind);

    // Keyboard reordering on the drag handle.
    document.addEventListener('keydown', (event) => {
        const handle = event.target.closest('[data-drag-handle]');
        if (!handle || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        const item = handle.closest('[data-stage-id]');
        const sibling = event.key === 'ArrowUp' ? item.previousElementSibling : item.nextElementSibling;
        if (!sibling) return;
        if (event.key === 'ArrowUp') sibling.before(item);
        else sibling.after(item);
        handle.focus();
        saveOrder(item.closest('[data-sortable-stages]'));
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
