@props(['id', 'title'])
<div id="{{ $id }}" data-modal hidden role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" class="fixed inset-0 z-50">
    <div class="overlay-backdrop" data-modal-backdrop></div>
    <div class="drawer-panel">
        <header class="flex items-center justify-between gap-4 px-6 h-16 border-b border-line shrink-0">
            <h2 id="{{ $id }}-title" class="text-lg font-semibold text-ink-900">{{ $title }}</h2>
            <button type="button" class="btn btn-ghost btn-icon -mr-2" data-modal-close aria-label="Close"><x-icon name="x" size="h-5 w-5" /></button>
        </header>
        <div class="flex-1 overflow-y-auto p-6">{{ $slot }}</div>
    </div>
</div>
