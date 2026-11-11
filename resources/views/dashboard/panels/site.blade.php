@if ($data['labour'])
    <a href="{{ $data['labour']['href'] }}" class="grid grid-cols-3 gap-3 text-center">
        <span class="rounded-card bg-surface-muted px-2 py-3"><span class="block text-[12px] text-ink-600">Workers Today</span><span class="mt-0.5 block text-base font-semibold tabular-nums text-ink-900">{{ number_format($data['labour']['workers']) }}</span></span>
        <span class="rounded-card bg-surface-muted px-2 py-3"><span class="block text-[12px] text-ink-600">Labour Today</span><span class="mt-0.5 block text-base font-semibold tabular-nums text-ink-900">{{ App\Support\Format::money($data['labour']['today'], false) }}</span></span>
        <span class="rounded-card bg-surface-muted px-2 py-3"><span class="block text-[12px] text-ink-600">This Week</span><span class="mt-0.5 block text-base font-semibold tabular-nums text-ink-900">{{ App\Support\Format::money($data['labour']['week'], false) }}</span></span>
    </a>
@endif
@if ($data['show_materials'])
    <p @class(['text-[12px] font-semibold uppercase tracking-wide text-ink-500', 'mt-4' => $data['labour']])>Most Used Materials This Month</p>
    @if (! $data['materials'])
        <x-empty-state icon="layers" title="Nothing issued this month" class="py-5" />
    @else
        <ul class="divide-y divide-line">
            @foreach ($data['materials'] as $m)
                <li class="flex items-center justify-between gap-3 py-2 text-sm"><span class="min-w-0 truncate text-ink-700">{{ $m['name'] }} <span class="text-[12px] tabular-nums text-ink-500">· {{ $m['qty'] }}</span></span>@if ($m['value'] !== null)<span class="shrink-0 font-medium tabular-nums text-ink-900">{{ App\Support\Format::money($m['value'], false) }}</span>@endif</li>
            @endforeach
        </ul>
    @endif
@endif
