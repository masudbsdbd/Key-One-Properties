<dl class="space-y-3">
    <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Capital held</dt><dd class="font-semibold tabular-nums text-ink-900"><x-money :value="$data['capital']" /></dd></div>
    <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Profit approved, unpaid</dt><dd @class(['font-semibold tabular-nums', 'text-warning-600' => App\Support\Money::isPositive($data['due']), 'text-ink-900' => ! App\Support\Money::isPositive($data['due'])])><x-money :value="$data['due']" /></dd></div>
    <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Distributions to pay</dt><dd class="font-semibold tabular-nums text-ink-900"><x-money :value="$data['approved_unpaid']" /></dd></div>
    <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Profit paid</dt><dd class="font-semibold tabular-nums text-success-600"><x-money :value="$data['paid']" /></dd></div>
</dl>
@if ($data['short'])
    <p class="mt-4 flex items-baseline justify-between gap-3 border-t border-line pt-3 text-sm"><span class="text-ink-500">Capital not yet received</span><span class="font-semibold tabular-nums text-warning-600"><x-money :value="$data['short_total']" /></span></p>
    <ul class="mt-1">
        @foreach ($data['short'] as $r)
            <li class="flex items-center justify-between gap-3 py-1 text-[13px]"><span class="min-w-0 truncate"><x-ref :href="$r['href']" class="text-ink-700">{{ $r['name'] }}</x-ref> <span class="text-ink-500">· {{ $r['unit'] }}</span></span><span class="shrink-0 tabular-nums text-ink-700"><x-money :value="$r['amount']" /></span></li>
        @endforeach
    </ul>
    @if ($data['short_more'])<p class="text-[12px] text-ink-500">{{ $data['short_more'] }} more</p>@endif
@endif
<a href="{{ route('distributions.index') }}" class="mt-4 inline-block text-[13px] font-medium text-accent-700 hover:underline">Distributions</a>
