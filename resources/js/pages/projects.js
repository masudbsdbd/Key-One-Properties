import Sortable from 'sortablejs';
import { http } from '../core/http';
import { toast } from '../components/toast';
import { reloadPartials } from '../components/partials';

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
    initStageReorder();
    initAutoSubmit();
}
