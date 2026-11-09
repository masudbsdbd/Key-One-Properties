{{-- Unit cost and profit for the signed-in investor. Needs: $flat, $position, $categories, $run, $runCost, $canFinancials, $canProfit --}}
@php
    $money = fn ($v) => App\Support\Format::money($v);
    $calc = $position['approved'] ?? $position['open'];
    $calcState = $position['approved'] ? null : ($position['open']?->status === 'submitted' ? 'pending' : 'estimated');
    $snap = $calc?->basis_snapshot ?? [];
@endphp
@if ($canProfit)
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-portal.figure label="Profit Paid" :value="$money($position['balances']['paid'])" state="paid" />
        <x-portal.figure label="Approved, Not Yet Paid" :value="$money($position['balances']['profit_due'])" state="approved" />
        @if ($position['open'])
            <x-portal.figure :label="$position['open']->status === 'submitted' ? 'Waiting for Approval' : 'Estimated Profit'" :value="$money($position['open_share']?->profit_amount ?? 0)"
                :state="$position['open']->status === 'submitted' ? 'pending' : 'estimated'" :meta="'As of '.App\Support\Format::date(Carbon\Carbon::parse($position['open']->basis_snapshot['as_of'])).' · '.$position['open']->calculation_no" />
        @endif
        @if ($position['approved'])
            <x-portal.figure label="Last Approved Profit" :value="$money($position['approved_share']?->profit_amount ?? 0)" state="calculated" :meta="$position['approved']->calculation_no.' · '.App\Support\Format::date($position['approved']->approved_at)" />
        @endif
    </div>
@endif

@if ($canFinancials)
    <x-card :title="$calc ? 'Cost and Profit' : 'Unit Cost'" :padded="false" class="mt-6">
        <x-slot:actions>@if ($calcState)<x-badge :tone="$calcState === 'pending' ? 'warning' : 'neutral'">{{ $calcState === 'pending' ? 'Pending Approval' : 'Estimated' }}</x-badge>@elseif ($calc)<x-badge tone="success">Approved</x-badge>@endif</x-slot:actions>
        @if ($calc)
            <dl class="divide-y divide-line text-sm">
                <div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">Contract value · {{ $snap['sale_no'] ?? '' }}</dt><dd><x-money :value="$snap['contract_value'] ?? $calc->revenue_amount" /></dd></div>
                @if (isset($snap['late_fees']) && (float) $snap['late_fees'])<div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">Late fees</dt><dd><x-money :value="$snap['late_fees']" /></dd></div>@endif
                <div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">Direct cost</dt><dd><x-money :value="'-'.$calc->direct_cost_amount" /></dd></div>
                <div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">Shared cost (by saleable area)</dt><dd><x-money :value="'-'.$calc->shared_cost_amount" /></dd></div>
                @foreach ($categories as $key => $label)
                    @php $c = $snap['cost_categories'][$key] ?? ['shared' => 0, 'direct' => 0]; @endphp
                    <div class="flex justify-between gap-4 px-5 py-2 pl-9 text-[13px]"><dt class="text-ink-500">{{ $label }}</dt><dd class="text-ink-600"><x-money :value="App\Support\Money::add((string) $c['shared'], (string) $c['direct'])" /></dd></div>
                @endforeach
                @if ((float) $calc->other_approved_cost_amount)<div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">Other cost</dt><dd><x-money :value="'-'.$calc->other_approved_cost_amount" /></dd></div>@endif
                <div class="flex justify-between gap-4 px-5 py-3 font-semibold"><dt>Net profit</dt><dd @class(['text-danger-600' => (float) $calc->net_profit_amount < 0])><x-money :value="$calc->net_profit_amount" /></dd></div>
            </dl>
            <p class="border-t border-line px-5 py-3 text-[13px] text-ink-500">{{ $calc->calculation_no }} · as of {{ App\Support\Format::date(Carbon\Carbon::parse($snap['as_of'])) }} · cost run {{ $snap['run_no'] ?? '' }} v{{ $snap['run_version'] ?? '' }}</p>
        @elseif ($run)
            <dl class="divide-y divide-line text-sm">
                <div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">Direct cost</dt><dd><x-money :value="$runCost['direct']" /></dd></div>
                <div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">Shared cost (by saleable area)</dt><dd><x-money :value="$runCost['shared']" /></dd></div>
                @foreach ($categories as $key => $label)
                    <div class="flex justify-between gap-4 px-5 py-2 pl-9 text-[13px]"><dt class="text-ink-500">{{ $label }}</dt><dd class="text-ink-600"><x-money :value="App\Support\Money::add($runCost['categories'][$key]['shared'], $runCost['categories'][$key]['direct'])" /></dd></div>
                @endforeach
            </dl>
            <p class="border-t border-line px-5 py-3 text-[13px] text-ink-500">Cost run {{ $run->run_no }} v{{ $run->calculation_version }} · cost up to {{ App\Support\Format::date($run->cost_cutoff) }}</p>
        @else
            <x-empty-state icon="layers" title="No approved cost yet" />
        @endif
    </x-card>
@endif
