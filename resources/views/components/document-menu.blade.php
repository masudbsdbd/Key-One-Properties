@props(['url', 'compact' => false])
@php($id = 'doc-'.\Illuminate\Support\Str::random(6))
<div class="relative" {{ $attributes }}>
    @if ($compact)
        <button type="button" class="btn btn-ghost btn-icon" data-menu-button aria-controls="{{ $id }}" aria-expanded="false" aria-label="Print or download"><x-icon name="printer" size="h-4 w-4" /></button>
    @else
        <button type="button" class="btn btn-secondary" data-menu-button aria-controls="{{ $id }}" aria-expanded="false"><x-icon name="printer" size="h-4 w-4" />Print<x-icon name="chevron-down" size="h-4 w-4" /></button>
    @endif
    <div id="{{ $id }}" data-menu hidden class="absolute right-0 z-30 mt-2 w-40 rounded-card border border-line bg-surface p-1.5 shadow-raised">
        <a href="{{ $url }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-surface-muted"><x-icon name="printer" size="h-4 w-4 text-ink-500" />Print</a>
        <a href="{{ $url }}?format=pdf" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-surface-muted"><x-icon name="download" size="h-4 w-4 text-ink-500" />PDF</a>
    </div>
</div>
