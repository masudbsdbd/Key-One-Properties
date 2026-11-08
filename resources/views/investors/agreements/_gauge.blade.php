@php
    $pct = fn ($v) => App\Modules\Investors\Controllers\AgreementController::pct($v);
    $overlapping = $summary['agreements']->filter(fn ($a) => ($a->effective_to === null || $a->effective_to->gte($from)) && ($to === null || $a->effective_from->lte($to)))->values();
@endphp
<div class="rounded-card border border-line bg-surface-muted p-4" data-share-gauge data-allocated="{{ $summary['allocated'] }}">
    <div class="mb-2 flex items-baseline justify-between gap-3">
        <p class="font-medium text-ink-900">{{ $flat->project->code }} · {{ $flat->building->code }} · Unit {{ $flat->flat_no }}</p>
        <p class="text-sm tabular-nums text-ink-700"><span data-gauge-total>{{ $pct($summary['allocated']) }}</span> of 100%</p>
    </div>
    <div class="flex h-3 overflow-hidden rounded-full bg-line" role="img" aria-label="Allocated {{ $pct($summary['allocated']) }}">
        <span class="h-full bg-ink-400" style="width: {{ min(100, (float) $summary['allocated']) }}%"></span>
        <span class="h-full bg-accent-500 transition-all" style="width: 0%" data-gauge-this></span>
    </div>
    <dl class="mt-3 grid grid-cols-3 gap-3 text-sm">
        <div><dt class="text-ink-500">Taken</dt><dd class="font-medium tabular-nums text-ink-900">{{ $pct($summary['allocated']) }}</dd></div>
        <div><dt class="text-ink-500">This Agreement</dt><dd class="font-medium tabular-nums text-accent-700" data-gauge-this-label>0%</dd></div>
        <div><dt class="text-ink-500">Free</dt><dd class="font-medium tabular-nums" data-gauge-free>{{ $pct($summary['available']) }}</dd></div>
    </dl>
    @if ($overlapping->isNotEmpty())
        <ul class="mt-3 space-y-1 border-t border-line pt-3 text-[13px]">
            @foreach ($overlapping as $a)
                <li class="flex items-center gap-2"><span class="h-2 w-2 shrink-0 rounded-full bg-ink-400"></span><span class="min-w-0 flex-1 truncate text-ink-700">{{ $a->investor->name }} · {{ App\Support\Format::date($a->effective_from) }} – {{ $a->effective_to ? App\Support\Format::date($a->effective_to) : 'open' }}</span><span class="tabular-nums text-ink-900">{{ $pct($a->profit_share_percent) }}</span></li>
            @endforeach
        </ul>
    @endif
</div>
