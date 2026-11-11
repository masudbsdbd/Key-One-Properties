{{-- Unit cost and profit for the signed-in investor. Needs: $flat, $position, $categories, $run, $runCost, $canFinancials, $canProfit --}}
@php
    $money = fn ($v) => App\Support\Format::money($v);
    $calc = $position['approved'] ?? $position['open'];
    $calcState = $position['approved'] ? null : ($position['open']?->status === 'submitted' ? 'pending' : 'estimated');
    $snap = $calc?->basis_snapshot ?? [];
    $mine = $position['approved'] ? $position['approved_share'] : $position['open_share'];
    $sharePct = $mine?->share_percent_snapshot ?? ($agreement->profit_share_percent ?? null);
@endphp
@if ($canProfit)
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if ($position['approved'])
            <x-portal.figure label="Your Profit So Far" :value="$money($position['approved_share']?->profit_amount ?? 0)" :meta="'Approved '.App\Support\Format::date($position['approved']->approved_at)" />
        @endif
        <x-portal.figure label="Profit Received" :value="$money($position['balances']['paid'])" />
        <x-portal.figure label="Profit to Receive" :value="$money($position['balances']['profit_due'])" :meta="App\Support\Money::isPositive($position['balances']['profit_due']) ? 'Payment pending' : null" />
        @if ($position['open'])
            <x-portal.figure :label="$position['open']->status === 'submitted' ? 'New Profit Awaiting Approval' : 'Estimated Profit'" :value="$money($position['open_share']?->profit_amount ?? 0)"
                :state="$position['open']->status === 'submitted' ? 'pending' : 'estimated'" :meta="'As of '.App\Support\Format::date(Carbon\Carbon::parse($position['open']->basis_snapshot['as_of']))" />
        @endif
    </div>
@endif

@if ($canFinancials)
    <x-card :title="$calc ? 'How Your Profit Is Worked Out' : 'Cost of This Unit'" :padded="false" class="mt-6">
        <x-slot:actions>@if ($calcState)<x-badge :tone="$calcState === 'pending' ? 'warning' : 'neutral'">{{ $calcState === 'pending' ? 'Pending Approval' : 'Estimated' }}</x-badge>@elseif ($calc)<x-badge tone="success">Approved</x-badge>@endif</x-slot:actions>
        @if ($calc)
            <dl class="divide-y divide-line text-sm">
                <div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">Unit Sold For</dt><dd><x-money :value="$snap['contract_value'] ?? $calc->revenue_amount" /></dd></div>
                @if (isset($snap['late_fees']) && (float) $snap['late_fees'])<div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">Late Fees Collected</dt><dd><x-money :value="$snap['late_fees']" /></dd></div>@endif
                @if ((float) $calc->direct_cost_amount)<div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">Cost Spent on This Unit</dt><dd><x-money :value="'-'.$calc->direct_cost_amount" /></dd></div>@endif
                <div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">This Unit's Share of Project Cost</dt><dd><x-money :value="'-'.$calc->shared_cost_amount" /></dd></div>
                @foreach ($categories as $key => $label)
                    @php $c = $snap['cost_categories'][$key] ?? ['shared' => 0, 'direct' => 0]; @endphp
                    <div class="flex justify-between gap-4 px-5 py-2 pl-9 text-[13px]"><dt class="text-ink-500">{{ $label }}</dt><dd class="text-ink-600"><x-money :value="App\Support\Money::add((string) $c['shared'], (string) $c['direct'])" /></dd></div>
                @endforeach
                @if ((float) $calc->other_approved_cost_amount)<div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">Other Cost</dt><dd><x-money :value="'-'.$calc->other_approved_cost_amount" /></dd></div>@endif
                @if ((float) $calc->estimated_cost_amount)<div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">Budgeted Cost Still to Come</dt><dd><x-money :value="'-'.$calc->estimated_cost_amount" /></dd></div>@endif
                <div class="flex justify-between gap-4 px-5 py-3 font-semibold"><dt>Profit on This Unit</dt><dd @class(['text-danger-600' => (float) $calc->net_profit_amount < 0])><x-money :value="$calc->net_profit_amount" /></dd></div>
                @if ($canProfit && $mine)
                    <div class="flex justify-between gap-4 bg-accent-50/60 px-5 py-3 text-base font-semibold text-ink-900"><dt>Your Share{{ $sharePct !== null ? ' ('.App\Modules\Investors\Controllers\AgreementController::pct($sharePct).')' : '' }}</dt><dd><x-money :value="$mine->profit_amount" /></dd></div>
                    @if ($position['approved'])
                        <div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">Already Paid to You</dt><dd class="text-success-600"><x-money :value="$position['balances']['paid']" /></dd></div>
                        <div class="flex justify-between gap-4 px-5 py-3 font-semibold"><dt>Still to Receive</dt><dd class="text-warning-600"><x-money :value="$position['balances']['profit_due']" /></dd></div>
                    @endif
                @endif
            </dl>
            <p class="border-t border-line px-5 py-3 text-[13px] text-ink-500">Worked out on {{ App\Support\Format::date(Carbon\Carbon::parse($snap['as_of'])) }} · {{ $calc->calculation_no }}</p>
        @elseif ($run)
            <dl class="divide-y divide-line text-sm">
                <div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">Cost Spent on This Unit</dt><dd><x-money :value="$runCost['direct']" /></dd></div>
                <div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-ink-600">This Unit's Share of Project Cost</dt><dd><x-money :value="$runCost['shared']" /></dd></div>
                @foreach ($categories as $key => $label)
                    <div class="flex justify-between gap-4 px-5 py-2 pl-9 text-[13px]"><dt class="text-ink-500">{{ $label }}</dt><dd class="text-ink-600"><x-money :value="App\Support\Money::add($runCost['categories'][$key]['shared'], $runCost['categories'][$key]['direct'])" /></dd></div>
                @endforeach
            </dl>
            <p class="border-t border-line px-5 py-3 text-[13px] text-ink-500">Cost up to {{ App\Support\Format::date($run->cost_cutoff) }} · {{ $run->run_no }}</p>
        @else
            <x-empty-state icon="layers" title="No approved cost yet" />
        @endif
    </x-card>
@endif
