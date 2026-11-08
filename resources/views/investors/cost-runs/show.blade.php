@php
    $s = $run->status;
    $cats = App\Models\ProjectCostAllocation::CATEGORIES;
    $pools = $run->snapshot_json['pools'] ?? [];
    $sum = fn ($values) => App\Support\Money::amount(App\Support\Money::add('0', ...array_values($values)));
@endphp
<x-layouts.app :title="$run->run_no">
    <x-page-header :title="$run->run_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Cost Allocation' => route('cost-runs.index'), $run->run_no => null]">
        <x-slot:actions>
            @can('investor.ledger.export')<x-export-menu :url="route('cost-runs.worksheet', $run)" />@endcan
            @if ($s === 'draft')
                @can('investor.cost.approve')
                    <x-button icon="check" data-action="post" data-url="{{ route('cost-runs.approve', $run) }}" data-reload data-confirm="Approve {{ $run->run_no }}?" data-confirm-message="{{ $run->project->name }} · cost up to {{ App\Support\Format::date($run->cost_cutoff) }}" data-confirm-text="Approve" data-tone="primary" :disabled="$stale" data-disabled-reason="Cost changed after this run">Approve</x-button>
                    <x-button variant="ghost" data-remote-modal="{{ route('cost-runs.reject.form', $run) }}" data-size="max-w-md">Reject</x-button>
                @endcan
            @endif
            @if (in_array($s, ['approved', 'rejected', 'superseded'], true))
                @can('investor.cost.allocate')<x-button variant="secondary" icon="refresh" data-remote-modal="{{ route('cost-runs.create', ['project' => $run->project->public_id]) }}" data-size="max-w-lg">New Run</x-button>@endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($stale && in_array($s, ['draft', 'approved'], true))
        <div class="mb-6 rounded-card border border-warning-600/30 bg-warning-50 px-5 py-3 text-sm text-warning-600">Cost or unit areas changed after this run</div>
    @endif
    @if ($s === 'rejected' && ! empty($run->snapshot_json['rejected_reason']))
        <div class="mb-6 rounded-card border border-danger-500/30 bg-danger-50 px-5 py-3 text-sm text-danger-600">{{ $run->snapshot_json['rejected_reason'] }}</div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Shared Cost" :value="App\Support\Format::money($run->eligible_shared_cost)" icon="layers" tone="accent" />
        <x-stat-card label="Direct Cost" :value="App\Support\Format::money($run->snapshot_json['direct_total'] ?? 0)" icon="door" tone="info" />
        <x-stat-card label="Saleable Area" :value="App\Support\Format::qty($run->total_saleable_area)" icon="grid" tone="violet" />
        <x-stat-card label="Units" :value="count($run->snapshot_json['flats'] ?? [])" icon="building" tone="success" />
    </div>

    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        <button type="button" role="tab" class="tab" id="tab-worksheet" data-tab="worksheet" aria-controls="panel-worksheet">Worksheet</button>
        <button type="button" role="tab" class="tab" id="tab-sources" data-tab="sources" aria-controls="panel-sources">Sources</button>
    </div>

    <section id="panel-worksheet" role="tabpanel" aria-labelledby="tab-worksheet" class="grid gap-6 xl:grid-cols-4">
        <x-card title="Run" class="min-w-0">
            <dl class="space-y-3 text-sm">
                <div><dt class="detail-label">Project</dt><dd class="detail-value">{{ $run->project->name }}</dd></div>
                <div><dt class="detail-label">Cost Up To</dt><dd class="detail-value">{{ App\Support\Format::date($run->cost_cutoff) }}</dd></div>
                <div><dt class="detail-label">Version</dt><dd class="detail-value">v{{ $run->calculation_version }} · {{ $run->snapshot_json['source_rows'] ?? 0 }} cost rows</dd></div>
                <div><dt class="detail-label">Status</dt><dd><x-status-badge :status="$s" :labels="App\Models\FlatCostAllocationRun::STATUSES" /></dd></div>
                <div><dt class="detail-label">Prepared / Approved</dt><dd class="detail-value">{{ $run->creator->name }}@if ($run->approver && $s !== 'rejected') · {{ $run->approver->name }}@endif</dd></div>
            </dl>
            <dl class="mt-4 space-y-2 border-t border-line pt-4 text-sm">
                @foreach ($cats as $c => $label)
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">{{ $label }}</dt><dd><x-money :value="$pools[$c] ?? 0" /></dd></div>
                @endforeach
                <div class="flex justify-between gap-3 border-t border-line pt-2 font-medium"><dt>Shared Pool</dt><dd><x-money :value="$run->eligible_shared_cost" /></dd></div>
            </dl>
        </x-card>
        <x-card :padded="false" class="min-w-0 xl:col-span-3">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Unit</th><th class="text-right">Area</th><th class="text-right">Share</th>
                            @foreach ($cats as $label)<th class="text-right">{{ $label }}</th>@endforeach
                            <th class="text-right">Direct</th><th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td class="font-medium text-ink-900">{{ $r['flat_no'] }}</td>
                                <td class="text-right tabular-nums">{{ App\Support\Format::qty($r['area']) }}</td>
                                <td class="text-right tabular-nums">{{ $r['pct'] }}%</td>
                                @foreach ($cats as $c => $label)<td class="text-right"><x-money :value="$r['shared'][$c]" :muted="(float) $r['shared'][$c] === 0.0" /></td>@endforeach
                                <td class="text-right"><x-money :value="$r['direct']" :muted="(float) $r['direct'] === 0.0" /></td>
                                <td class="text-right font-semibold"><x-money :value="$r['total']" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold">
                            <td>Total</td><td class="text-right tabular-nums">{{ App\Support\Format::qty($run->total_saleable_area) }}</td><td class="text-right">100%</td>
                            @foreach ($cats as $c => $label)<td class="text-right"><x-money :value="$sum($rows->pluck('shared.'.$c)->all())" /></td>@endforeach
                            <td class="text-right"><x-money :value="$sum($rows->pluck('direct')->all())" /></td>
                            <td class="text-right"><x-money :value="$sum($rows->pluck('total')->all())" /></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-card>
    </section>

    <section id="panel-sources" role="tabpanel" aria-labelledby="tab-sources" hidden>
        <x-data-table :url="route('cost-runs.sources', $run)" sort="date" dir="asc" :search="false" :columns="[
            ['label' => 'Date', 'sort' => 'date'], ['label' => 'Category'], ['label' => 'Source'], ['label' => 'Unit'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'],
        ]">
            <x-slot:filters>
                <select name="category" class="form-control w-44" data-table-filter aria-label="Category"><option value="">All categories</option>@foreach ($cats as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                <select name="scope" class="form-control w-36" data-table-filter aria-label="Scope"><option value="">All</option><option value="shared">Shared</option><option value="direct">Direct</option></select>
            </x-slot:filters>
        </x-data-table>
    </section>
</x-layouts.app>
