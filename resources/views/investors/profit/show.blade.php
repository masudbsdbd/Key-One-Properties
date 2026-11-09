@php
    $s = $calc->status;
    $pct = fn ($v) => App\Modules\Investors\Controllers\AgreementController::pct($v);
    $snap = $calc->basis_snapshot;
    $totalCost = App\Support\Money::add((string) $calc->direct_cost_amount, (string) $calc->shared_cost_amount, (string) $calc->other_approved_cost_amount);
    $investorTotal = App\Support\Money::add('0', ...$calc->allocations->map(fn ($a) => (string) $a->profit_amount)->all());
    $lines = [
        ['Revenue', 'revenue_amount', false],
        ['Direct Cost', 'direct_cost_amount', true],
        ['Shared Cost', 'shared_cost_amount', true],
        ['Other Cost', 'other_approved_cost_amount', true],
        ['Net Profit', 'net_profit_amount', false],
        ['Company Share', 'company_share_amount', false],
    ];
@endphp
<x-layouts.app :title="$calc->calculation_no">
    <x-page-header :title="$calc->calculation_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Flat Profit' => route('profit-calculations.index'), $calc->calculation_no => null]">
        <x-slot:actions>
            <x-document-menu :url="route('profit-calculations.document', $calc)" />
            @if ($s === 'draft')
                @can('investor.profit.submit')<x-button icon="arrow-right" data-action="post" data-url="{{ route('profit-calculations.decide', [$calc, 'submit']) }}" data-reload data-confirm="Submit {{ $calc->calculation_no }}?" data-confirm-text="Submit" data-tone="primary">Submit</x-button>@endcan
            @endif
            @if ($s === 'submitted')
                @can('investor.profit.approve')
                    <x-button icon="check" data-action="post" data-url="{{ route('profit-calculations.decide', [$calc, 'approve']) }}" data-reload data-confirm="Approve profit of Unit {{ $calc->flat->flat_no }}?" data-confirm-message="Investor payable {{ App\Support\Format::money($investorTotal) }} · company share {{ App\Support\Format::money($calc->company_share_amount) }}" data-confirm-text="Approve" data-tone="primary" :disabled="(bool) $blocker" :data-disabled-reason="$blocker">Approve</x-button>
                    <x-button variant="ghost" data-remote-modal="{{ route('profit-calculations.reason', [$calc, 'reject']) }}" data-size="max-w-md">Reject</x-button>
                @endcan
            @endif
            @if (in_array($s, ['draft', 'submitted'], true))
                @can('investor.profit.calculate')<x-button variant="ghost" icon="x" data-remote-modal="{{ route('profit-calculations.reason', [$calc, 'cancel']) }}" data-size="max-w-md" aria-label="Cancel calculation" />@endcan
            @endif
            @if (in_array($s, ['approved', 'rejected', 'cancelled', 'superseded'], true))
                @can('investor.profit.calculate')<x-button variant="secondary" icon="refresh" data-remote-modal="{{ route('profit-calculations.create', ['flat' => $calc->flat->public_id]) }}" data-size="max-w-lg">Recalculate</x-button>@endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($blocker)
        <div class="mb-6 rounded-card border border-warning-600/30 bg-warning-50 px-5 py-3 text-sm text-warning-600">Provisional · {{ $blocker }}</div>
    @endif
    @if ($calc->status_reason && in_array($s, ['rejected', 'cancelled'], true))
        <div class="mb-6 rounded-card border border-danger-500/30 bg-danger-50 px-5 py-3 text-sm text-danger-600">{{ $calc->status_reason }}</div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Revenue" :value="App\Support\Format::money($calc->revenue_amount)" icon="tag" tone="info" />
        <x-stat-card label="Total Cost" :value="App\Support\Format::money($totalCost)" icon="layers" tone="orange" />
        <x-stat-card label="Net Profit" :value="App\Support\Format::money($calc->net_profit_amount)" icon="bar-chart" :tone="(float) $calc->net_profit_amount < 0 ? 'danger' : 'success'" />
        <x-stat-card label="Investor Share" :value="$pct($calc->investor_share_percent)" icon="users" tone="accent" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Worksheet" :padded="false" class="min-w-0 xl:col-span-2">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Item</th>@foreach ($categories as $label)<th class="text-right">{{ $label }}</th>@endforeach<th class="text-right">Total</th></tr></thead>
                    <tbody>
                        <tr><td class="font-medium">Revenue · {{ $snap['sale_no'] ?? '' }}</td>@foreach ($categories as $c => $l)<td></td>@endforeach<td class="text-right font-medium"><x-money :value="$calc->revenue_amount" /></td></tr>
                        @foreach (['direct' => 'Direct cost', 'shared' => 'Shared cost'] as $type => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                @foreach ($categories as $c => $l)<td class="text-right"><x-money :value="$snap['cost_categories'][$c][$type] ?? 0" :muted="(float) ($snap['cost_categories'][$c][$type] ?? 0) === 0.0" /></td>@endforeach
                                <td class="text-right"><x-money :value="'-'.$calc->{$type.'_cost_amount'}" /></td>
                            </tr>
                        @endforeach
                        <tr><td>Other cost @if ($snap['other_cost_note'] ?? null)<span class="text-ink-500">· {{ $snap['other_cost_note'] }}</span>@endif</td>@foreach ($categories as $c => $l)<td></td>@endforeach<td class="text-right"><x-money :value="'-'.$calc->other_approved_cost_amount" /></td></tr>
                    </tbody>
                    <tfoot><tr class="font-semibold"><td>Net Profit</td>@foreach ($categories as $c => $l)<td></td>@endforeach<td @class(['text-right', 'text-danger-600' => (float) $calc->net_profit_amount < 0])><x-money :value="$calc->net_profit_amount" /></td></tr></tfoot>
                </table>
            </div>
        </x-card>
        <x-card title="Basis">
            <dl class="space-y-3 text-sm">
                <div><dt class="detail-label">Unit</dt><dd class="detail-value">{{ $calc->project->name }} · Unit {{ $calc->flat->flat_no }}</dd></div>
                <div><dt class="detail-label">Sale</dt><dd class="detail-value">@if ($calc->sale)<a href="{{ route('sales.show', $calc->sale) }}" class="text-accent-700 hover:underline">{{ $calc->sale->sale_no }}</a> · {{ $calc->sale->customer->full_name }}@else — @endif</dd></div>
                <div><dt class="detail-label">Cost Run</dt><dd class="detail-value">@if ($calc->costRun)<a href="{{ route('cost-runs.show', $calc->costRun) }}" class="text-accent-700 hover:underline">{{ $calc->costRun->run_no }}</a> · v{{ $calc->costRun->calculation_version }}@endif</dd></div>
                <div><dt class="detail-label">As Of</dt><dd class="detail-value">{{ isset($snap['as_of']) ? App\Support\Format::date(Carbon\Carbon::parse($snap['as_of'])) : '—' }}</dd></div>
                <div><dt class="detail-label">Revenue Recognition</dt><dd class="detail-value">{{ App\Modules\Investors\Services\ProfitPolicy::RECOGNITION[$calc->recognition_basis] ?? 'Not set' }}</dd></div>
                <div><dt class="detail-label">Eligibility</dt><dd class="flex items-center gap-2"><x-badge :tone="$calc->revenue_eligible ? 'success' : 'warning'" class="shrink-0 whitespace-nowrap">{{ $calc->revenue_eligible ? 'Eligible' : 'Not eligible' }}</x-badge><span class="text-ink-600">{{ $snap['eligibility'] ?? '' }}</span></dd></div>
                <div><dt class="detail-label">Status</dt><dd><x-status-badge :status="$s" :labels="App\Models\FlatProfitCalculation::STATUSES" /></dd></div>
                <div><dt class="detail-label">Prepared / Approved</dt><dd class="detail-value">{{ $calc->calculator->name }}@if ($calc->approver && $s === 'approved') · {{ $calc->approver->name }}@endif</dd></div>
            </dl>
        </x-card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-card title="Investor Allocation" :padded="false" class="min-w-0 xl:col-span-2">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Investor</th><th>Agreement</th><th class="text-right">Share</th><th class="text-right">Profit</th><th class="text-right">Approved Before</th><th class="text-right">Change</th></tr></thead>
                    <tbody>
                        @forelse ($calc->allocations as $a)
                            <tr>
                                <td><a href="{{ route('investors.show', $a->investor) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $a->investor->name }}</a></td>
                                <td><a href="{{ route('investor-agreements.show', $a->agreement) }}" class="text-accent-700 hover:underline">{{ $a->agreement->agreement_no }}</a></td>
                                <td class="text-right tabular-nums">{{ $pct($a->share_percent_snapshot) }}</td>
                                <td class="text-right font-medium"><x-money :value="$a->profit_amount" /></td>
                                <td class="text-right"><x-money :value="$a->previously_approved" muted /></td>
                                <td @class(['text-right', 'text-danger-600' => (float) $a->payable_change < 0])><x-money :value="$a->payable_change" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty-state icon="users" title="No investors on this unit" /></td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold"><td colspan="3">Investors</td><td class="text-right"><x-money :value="$investorTotal" /></td><td colspan="2"></td></tr>
                        <tr class="font-semibold"><td colspan="3">Company</td><td class="text-right"><x-money :value="$calc->company_share_amount" /></td><td colspan="2"></td></tr>
                    </tfoot>
                </table>
            </div>
        </x-card>
        <x-card title="Compared With" :padded="false">
            @if ($previous)
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th></th><th class="text-right"><a href="{{ route('profit-calculations.show', $previous) }}" class="hover:text-accent-700">{{ $previous->calculation_no }}</a></th><th class="text-right">This</th></tr></thead>
                        <tbody>
                            @foreach ($lines as [$label, $field])
                                <tr @class(['font-semibold' => $field === 'net_profit_amount'])>
                                    <td>{{ $label }}</td>
                                    <td class="text-right"><x-money :value="$previous->{$field}" muted /></td>
                                    <td @class(['text-right', 'text-accent-700' => App\Support\Money::cmp((string) $previous->{$field}, (string) $calc->{$field}) !== 0])><x-money :value="$calc->{$field}" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-empty-state icon="bar-chart" title="No earlier approved calculation" />
            @endif
        </x-card>
    </div>
</x-layouts.app>
