<p class="flex items-baseline justify-between gap-3"><span class="text-[13px] text-ink-500">{{ $data['units'] }} {{ Str::plural('unit', $data['units']) }} · last 12 months</span><span class="text-lg font-semibold tabular-nums text-ink-900">{{ App\Support\Format::money($data['value'], false) }}</span></p>
<div class="mt-4 flex h-28 items-end gap-1.5" role="list">
    @foreach ($data['months'] as $m)
        <a href="{{ $m['href'] }}" role="listitem" class="group flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1" data-tooltip="{{ $m['title'] }} · {{ $m['units'] }} {{ Str::plural('unit', $m['units']) }} · {{ App\Support\Format::money($m['value'], false) }}">
            <span class="text-[11px] font-medium tabular-nums text-ink-700">{{ $m['units'] ?: '' }}</span>
            <span class="w-full rounded-t bg-accent-500 group-hover:bg-accent-700" style="height: {{ (float) $m['value'] > 0 ? max(4, (float) $m['value'] / $data['max'] * 100) : 0 }}%"></span>
        </a>
    @endforeach
</div>
<div class="mt-1 flex gap-1.5 border-t border-line pt-1">
    @foreach ($data['months'] as $m)<span class="min-w-0 flex-1 text-center text-[11px] text-ink-500">{{ $m['label'] }}</span>@endforeach
</div>
