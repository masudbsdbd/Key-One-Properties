@props(['items', 'money' => true, 'tone' => 'bg-accent-500'])
{{-- items: [label => value]. Each bar shows its value as text, so the chart needs no separate table. --}}
@php
    $values = collect($items)->map(fn ($v) => (float) $v);
    $max = max(1, $values->max() ?: 1);
@endphp
<ul class="space-y-2.5" role="list">
    @foreach ($items as $label => $value)
        <li>
            <div class="flex items-baseline justify-between gap-3 text-sm"><span class="truncate text-ink-700">{{ $label }}</span><span class="shrink-0 font-medium tabular-nums text-ink-900">{{ $money ? App\Support\Format::money($value) : number_format((float) $value) }}</span></div>
            <div class="mt-1 h-2 overflow-hidden rounded-full bg-ink-900/[0.06]" aria-hidden="true"><div class="h-full rounded-full {{ $tone }}" style="width: {{ max(0, (float) $value) / $max * 100 }}%"></div></div>
        </li>
    @endforeach
</ul>
