@php
    $s = $agreement->status;
    $pct = fn ($v) => App\Modules\Investors\Controllers\AgreementController::pct($v);
    $flat = $agreement->flat;
@endphp
<x-layouts.app :title="$agreement->agreement_no">
    <x-page-header :title="$agreement->agreement_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Agreements' => route('investor-agreements.index'), $agreement->agreement_no => null]">
        <x-slot:actions>
            <x-document-menu :url="route('investor-agreements.document', $agreement)" />
            @if (in_array($s, ['draft', 'rejected'], true))
                @can('investor.agreement.edit')
                    <x-button icon="arrow-right" data-action="post" data-url="{{ route('investor-agreements.decide', [$agreement, 'submit']) }}" data-reload data-confirm="Submit {{ $agreement->agreement_no }} for approval?" data-confirm-text="Submit" data-tone="primary">Submit</x-button>
                    <x-button variant="secondary" icon="edit" data-remote-modal="{{ route('investor-agreements.edit', $agreement) }}" data-size="max-w-3xl">Edit</x-button>
                @endcan
            @endif
            @if ($s === 'pending')
                @can('investor.agreement.approve')
                    <x-button icon="check" data-action="post" data-url="{{ route('investor-agreements.decide', [$agreement, 'approve']) }}" data-reload data-confirm="Approve {{ $agreement->agreement_no }}?" data-confirm-message="{{ $agreement->investor->name }} · {{ $pct($agreement->profit_share_percent) }} of Unit {{ $flat->flat_no }}{{ $agreement->supersedes ? ' · replaces '.$agreement->supersedes->agreement_no : '' }}" data-confirm-text="Approve" data-tone="primary">Approve</x-button>
                    <x-button variant="ghost" data-remote-modal="{{ route('investor-agreements.reason', [$agreement, 'reject']) }}" data-size="max-w-md">Reject</x-button>
                @endcan
            @endif
            @if ($s === 'active')
                @can('investor.capital.record')<x-button icon="tag" data-remote-modal="{{ route('investor-capital.create', ['agreement' => $agreement->public_id]) }}" data-size="max-w-2xl">Receive Capital</x-button>@endcan
                @can('investor.agreement.create')<x-button variant="secondary" icon="refresh" data-action="post" data-url="{{ route('investor-agreements.decide', [$agreement, 'amend']) }}" data-confirm="Amend {{ $agreement->agreement_no }}?" data-confirm-message="A new draft version will be created. This version stays active until the amendment is approved." data-confirm-text="Amend" data-tone="primary" :disabled="(bool) $openAmendment" data-disabled-reason="Amendment {{ $openAmendment?->agreement_no }} is open">Amend</x-button>@endcan
            @endif
            @if (in_array($s, ['active', 'closed'], true) && App\Support\Money::isPositive($balance))
                @can('investor.capital.return')<x-button variant="secondary" icon="arrow-left" data-remote-modal="{{ route('investor-capital.create', ['agreement' => $agreement->public_id, 'type' => 'capital_return']) }}" data-size="max-w-2xl">Return Capital</x-button>@endcan
            @endif
            @if ($s === 'active')
                @can('investor.agreement.close')<x-button variant="ghost" icon="archive" data-remote-modal="{{ route('investor-agreements.reason', [$agreement, 'close']) }}" data-size="max-w-md" aria-label="Close agreement" />@endcan
            @endif
            @if (in_array($s, ['draft', 'pending', 'rejected'], true))
                @can('investor.agreement.edit')<x-button variant="ghost" icon="x" data-remote-modal="{{ route('investor-agreements.reason', [$agreement, 'cancel']) }}" data-size="max-w-md" aria-label="Cancel agreement" />@endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($agreement->status_reason && in_array($s, ['rejected', 'cancelled', 'closed'], true))
        <div class="mb-6 rounded-card border {{ $s === 'closed' ? 'border-line bg-surface-muted text-ink-700' : 'border-danger-500/30 bg-danger-50 text-danger-600' }} px-5 py-3 text-sm">{{ $agreement->status_reason }}</div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Profit Share" :value="$pct($agreement->profit_share_percent)" icon="bar-chart" tone="accent" />
        <x-stat-card label="Committed Capital" :value="App\Support\Format::money($agreement->committed_capital)" icon="file" tone="info" />
        <x-stat-card label="Capital Invested" :value="App\Support\Format::money($balance)" icon="tag" tone="success" />
        <x-stat-card label="Unit Allocated" :value="$pct($summary['allocated'])" icon="door" :tone="App\Support\Money::cmp($summary['allocated'], '100') === 0 ? 'warning' : 'violet'" />
    </div>

    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        <button type="button" role="tab" class="tab" id="tab-overview" data-tab="overview" aria-controls="panel-overview">Overview</button>
        <button type="button" role="tab" class="tab" id="tab-capital" data-tab="capital" aria-controls="panel-capital">Capital</button>
        <button type="button" role="tab" class="tab" id="tab-versions" data-tab="versions" aria-controls="panel-versions">Versions</button>
        <button type="button" role="tab" class="tab" id="tab-files" data-tab="files" aria-controls="panel-files">Files</button>
        <button type="button" role="tab" class="tab" id="tab-history" data-tab="history" aria-controls="panel-history">History</button>
    </div>

    <section id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" class="grid gap-6 xl:grid-cols-3">
        <x-card class="min-w-0 xl:col-span-2">
            <div class="grid gap-5 sm:grid-cols-2">
                <div><p class="detail-label">Investor</p><a href="{{ route('investors.show', $agreement->investor) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $agreement->investor->name }}</a><p class="text-[13px] text-ink-500">{{ $agreement->investor->investor_code }}</p></div>
                <div><p class="detail-label">Unit</p><p class="detail-value font-semibold"><x-ref :to="$agreement->project">{{ $agreement->project->name }}</x-ref> · <x-ref :to="$flat">Unit {{ $flat->flat_no }}</x-ref></p><p class="text-[13px] text-ink-500">{{ $flat->building->name }} · {{ $flat->floor->label }} · {{ number_format((float) $flat->saleable_area) }} {{ $flat->area_unit }}</p></div>
                <div><p class="detail-label">Effective</p><p class="detail-value">{{ App\Support\Format::date($agreement->effective_from) }} – {{ $agreement->effective_to ? App\Support\Format::date($agreement->effective_to) : 'open' }}</p></div>
                <div><p class="detail-label">Version</p><p class="detail-value">v{{ $agreement->version }}@if ($agreement->supersedes) · replaces <a href="{{ route('investor-agreements.show', $agreement->supersedes) }}" class="text-accent-700 hover:underline">{{ $agreement->supersedes->agreement_no }}</a>@endif</p></div>
                <div><p class="detail-label">Status</p><x-status-badge :status="$s" :labels="App\Models\InvestorFlatAgreement::STATUSES" /></div>
                <div><p class="detail-label">Prepared / Approved</p><p class="detail-value">{{ $agreement->creator->name }}@if ($agreement->approver) · {{ $agreement->approver->name }}@endif</p></div>
            </div>
            @if ($agreement->terms)<p class="mt-4 whitespace-pre-line border-t border-line pt-4 text-sm text-ink-700">{{ $agreement->terms }}</p>@endif
        </x-card>
        <x-card title="Unit Shares Today" :padded="false">
            @forelse ($summary['agreements']->filter(fn ($a) => $a->coversDate(now(config('app.display_timezone'))->startOfDay())) as $a)
                <a href="{{ route('investor-agreements.show', $a) }}" @class(['flex items-center gap-3 border-b border-line px-5 py-3 last:border-0 hover:bg-surface-muted', 'bg-accent-50' => $a->is($agreement)])>
                    <span class="min-w-0 flex-1 truncate text-ink-900">{{ $a->investor->name }}</span>
                    <span class="font-medium tabular-nums">{{ $pct($a->profit_share_percent) }}</span>
                </a>
            @empty
                <x-empty-state icon="users" title="No active shares" />
            @endforelse
        </x-card>
    </section>

    <section id="panel-capital" role="tabpanel" aria-labelledby="tab-capital" hidden>
        <x-card :padded="false">
            @forelse ($capital as $t)
                <a href="{{ route('investor-capital.show', $t) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0 hover:bg-surface-muted">
                    <span class="min-w-0 flex-1"><span class="block font-medium text-ink-900">{{ $t->transaction_no }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($t->transaction_date) }} · {{ App\Models\InvestorCapitalTransaction::TYPES[$t->transaction_type] }}</span></span>
                    <x-money :value="$t->transaction_type === 'capital_return' ? '-'.$t->amount : $t->amount" class="font-medium" />
                    <x-status-badge :status="$t->status" :labels="App\Models\InvestorCapitalTransaction::STATUSES" />
                </a>
            @empty
                <x-empty-state icon="tag" title="No capital transactions" />
            @endforelse
        </x-card>
    </section>

    <section id="panel-versions" role="tabpanel" aria-labelledby="tab-versions" hidden>
        <x-card :padded="false">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Version</th><th>Agreement</th><th class="text-right">Share</th><th class="text-right">Committed</th><th>Effective</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($versions as $v)
                            <tr @class(['bg-accent-50' => $v->is($agreement)])>
                                <td class="font-medium">v{{ $v->version }}</td>
                                <td><a href="{{ route('investor-agreements.show', $v) }}" class="text-accent-700 hover:underline">{{ $v->agreement_no }}</a></td>
                                <td class="text-right tabular-nums">{{ $pct($v->profit_share_percent) }}</td>
                                <td class="text-right"><x-money :value="$v->committed_capital" /></td>
                                <td class="whitespace-nowrap">{{ App\Support\Format::date($v->effective_from) }} – {{ $v->effective_to ? App\Support\Format::date($v->effective_to) : 'open' }}</td>
                                <td><x-status-badge :status="$v->status" :labels="App\Models\InvestorFlatAgreement::STATUSES" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </section>

    <section id="panel-files" role="tabpanel" aria-labelledby="tab-files" hidden>
        <x-attachments :owner="$agreement" type="investor-agreement" />
    </section>

    <section id="panel-history" role="tabpanel" aria-labelledby="tab-history" hidden>
        <x-card :padded="false">@include('projects.partials._activity', ['entries' => $activity])</x-card>
    </section>
</x-layouts.app>
