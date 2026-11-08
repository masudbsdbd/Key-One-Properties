<div id="confirm-dialog" data-modal hidden role="alertdialog" aria-modal="true" aria-labelledby="confirm-dialog-title" class="fixed inset-0 z-[60]">
    <div class="overlay-backdrop z-[60]" data-modal-backdrop></div>
    <div class="relative z-[60] flex min-h-full items-center justify-center p-4 pointer-events-none">
        <div class="dialog-panel max-w-md pointer-events-auto p-6 text-center">
            <span class="icon-tile tone-danger mx-auto mb-4"><x-icon name="alert" size="h-6 w-6" /></span>
            <h2 id="confirm-dialog-title" class="text-lg font-semibold text-ink-900" data-confirm-title></h2>
            <p class="mt-2 text-sm text-ink-500" data-confirm-message></p>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="button" class="btn btn-danger" data-confirm-accept>Confirm</button>
            </div>
        </div>
    </div>
</div>
