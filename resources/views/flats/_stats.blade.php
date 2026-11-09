@php
    $tiles = [
        ['Total Units', $counts['total'], 'building', 'bg-info-50', 'tone-info'],
        ['Not Released', $counts['not_released'], 'clock', 'bg-accent-50', 'tone-accent'],
        ['Available', $counts['available'], 'check', 'bg-success-50', 'tone-success'],
        ['Booked', $counts['booked'], 'calendar', 'bg-warning-50', 'tone-warning'],
        ['Sold', $counts['sold'], 'tag', 'bg-violet-50', 'tone-violet'],
        ['Unavailable', $counts['unavailable'], 'lock', 'bg-danger-50', 'tone-danger'],
    ];
@endphp
<div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    @foreach ($tiles as [$label, $value, $icon, $bg, $tone])
        <div class="unit-stat {{ $bg }}">
            <span class="icon-tile icon-tile-md {{ $tone }} bg-white/70"><x-icon :name="$icon" size="h-6 w-6" /></span>
            <span><span class="block text-2xl font-semibold tabular-nums text-ink-900">{{ number_format($value) }}</span><span class="text-sm text-ink-700">{{ $label }}</span></span>
        </div>
    @endforeach
</div>
