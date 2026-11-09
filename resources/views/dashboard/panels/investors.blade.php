<dl class="space-y-3">
    <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Capital held</dt><dd class="font-semibold tabular-nums text-ink-900"><x-money :value="$data['capital']" /></dd></div>
    <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Profit approved, unpaid</dt><dd @class(['font-semibold tabular-nums', 'text-warning-600' => App\Support\Money::isPositive($data['due']), 'text-ink-900' => ! App\Support\Money::isPositive($data['due'])])><x-money :value="$data['due']" /></dd></div>
    <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Distributions to pay</dt><dd class="font-semibold tabular-nums text-ink-900"><x-money :value="$data['approved_unpaid']" /></dd></div>
    <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Profit paid</dt><dd class="font-semibold tabular-nums text-success-600"><x-money :value="$data['paid']" /></dd></div>
</dl>
<a href="{{ route('distributions.index') }}" class="mt-4 inline-block text-[13px] font-medium text-accent-700 hover:underline">Distributions</a>
