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
        <x-stat-card label="Capital Invested" :value="App\Support\Format::money($capital)" icon="tag" tone="accent">
            <x-slot:meta><span class="text-ink-500">of {{ App\Support\Format::money($committed) }} committed</span></x-slot:meta>
        </x-stat-card>
        <x-stat-card label="Profit Paid" :value="App\Support\Format::money($profitPaid)" icon="check" tone="success" />
        <x-stat-card label="Profit to Pay" :value="App\Support\Format::money($profitDue)" icon="clock" :tone="App\Support\Money::isPositive($profitDue) ? 'warning' : 'success'" />
        <x-stat-card label="Total Profit" :value="App\Support\Format::money(App\Support\Money::add($profitPaid, $profitDue))" icon="bar-chart" tone="info" />
    </div>

    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        <button type="button" role="tab" class="tab" id="tab-overview" data-tab="overview" aria-controls="panel-overview">Overview</button>
        <button type="button" role="tab" class="tab" id="tab-capital" data-tab="capital" aria-controls="panel-capital">Capital</button>
        @can('investor.ledger.view')<button type="button" role="tab" class="tab" id="tab-ledger" data-tab="ledger" aria-controls="panel-ledger">Ledger</button>@endcan
        @canany(['portal.links.manage', 'portal.grants.manage'])<button type="button" role="tab" class="tab" id="tab-portal" data-tab="portal" aria-controls="panel-portal">Portal Login</button>@endcanany
        <button type="button" role="tab" class="tab" id="tab-files" data-tab="files" aria-controls="panel-files">Files</button>
        <button type="button" role="tab" class="tab" id="tab-history" data-tab="history" aria-controls="panel-history">History</button>
    </div>

    <section id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" class="grid gap-6 xl:grid-cols-3">
        <x-card title="Agreements" :padded="false" class="min-w-0 xl:col-span-2">
            @forelse ($agreements as $a)
                @php
                    $pos = $positions[$a->flat_id] ?? ['capital' => '0', 'paid' => '0', 'profit_due' => '0'];
                    $live = in_array($a->status, ['active', 'closed'], true);
                    $short = App\Support\Money::sub($a->committed_capital, $pos['capital']);
                @endphp
                <div class="border-b border-line px-5 py-4 last:border-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('investor-agreements.show', $a) }}" class="min-w-0 flex-1 hover:text-accent-700">
                            <span class="block font-medium text-ink-900">{{ $a->project->name }} · Unit {{ $a->flat->flat_no }}</span>
                            <span class="block text-[13px] text-ink-500">{{ $a->agreement_no }} · v{{ $a->version }} · {{ $a->effective_to ? App\Support\Format::date($a->effective_from).' – '.App\Support\Format::date($a->effective_to) : 'since '.App\Support\Format::date($a->effective_from) }}</span>
                        </a>
                        <x-status-badge :status="$a->status" :labels="App\Models\InvestorFlatAgreement::STATUSES" />
                        @if ($a->status === 'active')
                            @can('investor.capital.record')<x-button size="sm" variant="secondary" icon="tag" data-remote-modal="{{ route('investor-capital.create', ['agreement' => $a->public_id]) }}" data-size="max-w-2xl">Receive Capital</x-button>@endcan
                        @endif
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                        <div><dt class="text-[12px] text-ink-500">Profit Share</dt><dd class="font-semibold tabular-nums text-ink-900">{{ App\Modules\Investors\Controllers\AgreementController::pct($a->profit_share_percent) }}</dd></div>
                        <div><dt class="text-[12px] text-ink-500">Invested</dt><dd class="font-semibold tabular-nums text-ink-900">{{ $live ? App\Support\Format::money($pos['capital']) : '—' }}</dd><dd class="text-[12px] text-ink-500">of {{ App\Support\Format::money($a->committed_capital) }}@if ($a->status === 'active' && App\Support\Money::isPositive($short)) · <span class="font-medium text-warning-600">{{ App\Support\Format::money($short) }} short</span>@endif</dd></div>
                        <div><dt class="text-[12px] text-ink-500">Profit Paid</dt><dd class="font-semibold tabular-nums text-success-600">{{ $live ? App\Support\Format::money($pos['paid']) : '—' }}</dd></div>
                        <div><dt class="text-[12px] text-ink-500">Profit to Pay</dt><dd @class(['font-semibold tabular-nums', 'text-warning-600' => $live && App\Support\Money::isPositive($pos['profit_due']), 'text-ink-900' => ! ($live && App\Support\Money::isPositive($pos['profit_due']))])>{{ $live ? App\Support\Format::money($pos['profit_due']) : '—' }}</dd></div>
                    </dl>
                </div>
            @empty
                <x-empty-state icon="file" title="No agreements" />
            @endforelse
        </x-card>
        <x-card title="Profile">
            <div class="space-y-4">
                <div><p class="detail-label">Code</p><p class="detail-value">{{ $investor->investor_code }}</p></div>
                @if ($investor->mobile)<div><p class="detail-label">Mobile</p><p class="detail-value">{{ App\Support\Phone::display($investor->mobile) }}</p></div>@endif
                @if ($investor->email)<div><p class="detail-label">Email</p><p class="detail-value break-all">{{ $investor->email }}</p></div>@endif
                @if ($investor->tax_id)<div><p class="detail-label">TIN</p><p class="detail-value">{{ $investor->tax_id }}</p></div>@endif
                @if ($investor->address)<div><p class="detail-label">Address</p><p class="detail-value">{{ $investor->address }}</p></div>@endif
                @if ($portal)
                    <div>
                        <p class="detail-label">Portal Login</p>
                        <a href="#portal" class="mt-0.5 inline-flex items-center gap-2">
                            @if ($portal['state'] === 'active')<x-badge tone="success">Active</x-badge>@elseif ($portal['state'] === 'pending')<x-badge tone="warning">Pending Verification</x-badge>@else<span class="text-sm font-medium text-accent-700 hover:underline">Set Up</span>@endif
                        </a>
                        @if ($portal['login'])<p class="mt-1 text-[13px] text-ink-500">{{ $portal['login']->user->email ?: App\Support\Phone::display($portal['login']->user->mobile) }} · {{ $portal['login']->user->last_login_at ? 'last sign-in '.App\Support\Format::date($portal['login']->user->last_login_at) : 'never signed in' }}</p>@endif
                    </div>
                @endif
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

    @canany(['portal.links.manage', 'portal.grants.manage'])
        <section id="panel-portal" role="tabpanel" aria-labelledby="tab-portal" hidden>
            <div data-partial-url="{{ route('portal-access.panel', ['investor', $investor->public_id]) }}" data-lazy><x-card><div class="skeleton h-14 w-full"></div></x-card></div>
        </section>
    @endcanany
    <section id="panel-files" role="tabpanel" aria-labelledby="tab-files" hidden>
        <x-attachments :owner="$investor" type="investor" />
    </section>

    <section id="panel-history" role="tabpanel" aria-labelledby="tab-history" hidden>
        <x-card :padded="false">@include('projects.partials._activity', ['entries' => $activity])</x-card>
    </section>
</x-layouts.app>
