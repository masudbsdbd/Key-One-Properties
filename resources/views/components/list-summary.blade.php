@props(['tiles' => []])
<div @class(['grid gap-3', 'grid-cols-2 lg:grid-cols-4' => count($tiles) === 4, 'grid-cols-2 lg:grid-cols-3 xl:grid-cols-5' => count($tiles) >= 5, 'grid-cols-2 sm:grid-cols-3' => count($tiles) === 3, 'grid-cols-2' => count($tiles) <= 2])>
    @foreach ($tiles as $t)
        @php $tag = $t['preset'] ? 'button' : (($t['href'] ?? null) ? 'a' : 'div'); @endphp
        <{{ $tag }} @if ($t['preset']) type="button" data-table-preset='@json($t['preset'])' aria-pressed="{{ $t['active'] ? 'true' : 'false' }}" @elseif ($tag === 'a') href="{{ $t['href'] }}" @endif @class(['summary-tile', 'st-'.$t['tone'], 'is-clickable' => $t['preset'] || $tag === 'a', 'is-active' => $t['active']])>
            <span class="summary-tile-icon"><x-icon :name="$t['icon']" size="h-5 w-5" /></span>
            <span class="min-w-0 text-left">
                <span class="block truncate text-[13px] font-medium text-ink-500">{{ $t['label'] }}</span>
                <span class="block truncate text-xl font-semibold tabular-nums text-ink-900">{{ $t['value'] }}</span>
                @if ($t['note'])<span class="block truncate text-[12px] text-ink-500">{{ $t['note'] }}</span>@endif
            </span>
        </{{ $tag }}>
    @endforeach
</div>
