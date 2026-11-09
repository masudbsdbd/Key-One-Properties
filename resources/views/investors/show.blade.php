<x-layouts.app :title="$investor->name">
    <x-page-header :title="$investor->name" :breadcrumbs="['Dashboard' => route('dashboard'), 'Investors' => route('investors.index'), $investor->investor_code => null]">
        <x-slot:actions>
            @if ($investor->status === 'active')
                @can('investor.agreement.create')<x-button icon="plus" data-remote-modal="{{ route('investor-agreements.create', ['investor' => $investor->public_id]) }}" data-size="max-w-3xl">New Agreement</x-button>@endcan
            @endif
            @can('investor.edit')<x-button variant="secondary" icon="edit" data-remote-modal="{{ route('investors.edit', $investor) }}" data-size="max-w-2xl">Edit</x-button>@endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Capital Invested" :value="App\Support\Format::money($capital)" icon="tag" tone="accent" />
        <x-stat-card label="Committed (Active)" :value="App\Support\Format::money($committed)" icon="file" tone="info" />
        <x-stat-card label="Profit Due" :value="App\Support\Format::money($profitDue)" icon="bar-chart" :tone="App\Support\Money::isPositive($profitDue) ? 'warning' : 'success'" />
        <x-stat-card label="Active Units" :value="$agreements->where('status', 'active')->count()" icon="door" tone="success" />
    </div>

    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        <button type="button" role="tab" class="tab" id="tab-overview" data-tab="overview" aria-controls="panel-overview">Overview</button>
        <button type="button" role="tab" class="tab" id="tab-capital" data-tab="capital" aria-controls="panel-capital">Capital</button>
        @can('investor.ledger.view')<button type="button" role="tab" class="tab" id="tab-ledger" data-tab="ledger" aria-controls="panel-ledger">Ledger</button>@endcan
        <button type="button" role="tab" class="tab" id="tab-files" data-tab="files" aria-controls="panel-files">Files</button>
        <button type="button" role="tab" class="tab" id="tab-history" data-tab="history" aria-controls="panel-history">History</button>
    </div>

    <section id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" class="grid gap-6 xl:grid-cols-3">
        <x-card title="Agreements" :padded="false" class="min-w-0 xl:col-span-2">
            @forelse ($agreements as $a)
                <a href="{{ route('investor-agreements.show', $a) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0 hover:bg-surface-muted">
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium text-ink-900">{{ $a->project->code }} · {{ $a->flat->building->code }} · Unit {{ $a->flat->flat_no }}</span>
                        <span class="block text-[13px] text-ink-500">{{ $a->agreement_no }} · v{{ $a->version }} · {{ App\Support\Format::date($a->effective_from) }} – {{ $a->effective_to ? App\Support\Format::date($a->effective_to) : 'open' }}</span>
                    </span>
                    <span class="text-right"><span class="block font-semibold tabular-nums text-ink-900">{{ App\Modules\Investors\Controllers\AgreementController::pct($a->profit_share_percent) }}</span><x-money :value="$a->committed_capital" muted class="text-[13px]" /></span>
                    <x-status-badge :status="$a->status" :labels="App\Models\InvestorFlatAgreement::STATUSES" />
                </a>
            @empty
                <x-empty-state icon="file" title="No agreements" />
            @endforelse
        </x-card>
        <x-card title="Profile">
            <div class="space-y-4">
                <div><p class="detail-label">Code</p><p class="detail-value">{{ $investor->investor_code }}</p></div>
                <div><p class="detail-label">Mobile</p><p class="detail-value">{{ App\Support\Phone::display($investor->mobile) ?: '—' }}</p></div>
                <div><p class="detail-label">Email</p><p class="detail-value break-all">{{ $investor->email ?: '—' }}</p></div>
                <div><p class="detail-label">TIN</p><p class="detail-value">{{ $investor->tax_id ?: '—' }}</p></div>
                <div><p class="detail-label">Address</p><p class="detail-value">{{ $investor->address ?: '—' }}</p></div>
                <div><p class="detail-label">Status</p><x-status-badge :status="$investor->status" :labels="App\Models\Investor::STATUSES" /></div>
            </div>
        </x-card>
    </section>

    <section id="panel-capital" role="tabpanel" aria-labelledby="tab-capital" hidden>
        <x-data-table :url="route('investor-capital.table', ['investor' => $investor->public_id])" sort="date" dir="desc" :search="false" :columns="[
            ['label' => 'Transaction', 'sort' => 'date'], ['label' => 'Unit'], ['label' => 'Type'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'],
        ]">
            <x-slot:toolbar>@can('investor.ledger.export')<x-export-menu :url="route('investor-capital.export', ['investor' => $investor->public_id])" />@endcan</x-slot:toolbar>
        </x-data-table>
    </section>

    @can('investor.ledger.view')
        <section id="panel-ledger" role="tabpanel" aria-labelledby="tab-ledger" hidden>
            <x-data-table :url="route('investors.ledger', $investor)" :search="false" :columns="[
                ['label' => 'Date'], ['label' => 'Entry'], ['label' => 'Description'], ['label' => 'Capital', 'class' => 'text-right'], ['label' => 'Profit', 'class' => 'text-right'], ['label' => 'Capital Balance', 'class' => 'text-right'], ['label' => 'Profit Due', 'class' => 'text-right'],
            ]">
                <x-slot:filters>
                    <div class="w-64"><select name="flat" class="form-control" data-table-filter data-select data-select-url="{{ route('options.investment-flats') }}" data-placeholder="All units" aria-label="Unit"><option value=""></option></select></div>
                </x-slot:filters>
                <x-slot:toolbar>@can('investor.ledger.export')<x-export-menu :url="route('investors.ledger.export', $investor)" />@endcan</x-slot:toolbar>
            </x-data-table>
        </section>
    @endcan

    <section id="panel-files" role="tabpanel" aria-labelledby="tab-files" hidden>
        <x-attachments :owner="$investor" type="investor" />
    </section>

    <section id="panel-history" role="tabpanel" aria-labelledby="tab-history" hidden>
        <x-card :padded="false">@include('projects.partials._activity', ['entries' => $activity])</x-card>
    </section>
</x-layouts.app>
