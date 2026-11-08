@props(['id', 'title', 'size' => 'max-w-lg', 'static' => false])
<div id="{{ $id }}" data-modal @if ($static) data-static="true" @endif hidden role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" class="fixed inset-0 z-50">
    <div class="overlay-backdrop" data-modal-backdrop></div>
    <div class="relative z-50 flex min-h-full items-center justify-center p-4 pointer-events-none">
        <div class="dialog-panel {{ $size }} pointer-events-auto">
            <header class="flex items-center justify-between gap-4 px-6 pt-5 pb-4">
                <h2 id="{{ $id }}-title" class="text-lg font-semibold text-ink-900">{{ $title }}</h2>
                <button type="button" class="btn btn-ghost btn-icon -mr-2" data-modal-close aria-label="Close"><x-icon name="x" size="h-5 w-5" /></button>
            </header>
            <div class="overflow-y-auto px-6 pb-6">{{ $slot }}</div>
        </div>
    </div>
</div>
