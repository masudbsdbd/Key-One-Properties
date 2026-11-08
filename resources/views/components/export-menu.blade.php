@props(['url', 'table' => true, 'label' => 'Export'])
{{-- Inside an x-data-table toolbar the links carry the table's current filters. --}}
@php($id = 'export-'.\Illuminate\Support\Str::random(6))
<div class="relative" {{ $attributes }}>
    <button type="button" class="btn btn-secondary" data-menu-button aria-controls="{{ $id }}" aria-expanded="false"><x-icon name="download" size="h-4 w-4" />{{ $label }}<x-icon name="chevron-down" size="h-4 w-4" /></button>
    <div id="{{ $id }}" data-menu hidden class="absolute right-0 z-30 mt-2 w-44 rounded-card border border-line bg-surface p-1.5 shadow-raised">
        @foreach (['csv' => ['CSV', 'file', false], 'pdf' => ['PDF', 'download', false], 'print' => ['Print', 'note', true]] as $format => [$text, $icon, $blank])
            @php($href = $url.(str_contains($url, '?') ? '&' : '?').'format='.$format)
            <a href="{{ $href }}" @if ($table) data-table-export="{{ $href }}" @endif @if ($blank) target="_blank" rel="noopener" @endif class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-surface-muted"><x-icon :name="$icon" size="h-4 w-4 text-ink-500" />{{ $text }}</a>
        @endforeach
    </div>
</div>
