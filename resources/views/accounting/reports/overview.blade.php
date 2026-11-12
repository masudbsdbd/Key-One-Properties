@php
    $user = auth()->user();
    $money = fn ($v) => App\Support\Format::money($v, false);
    $reports = array_filter([
        $user->can('accounting.reports.view') ? ['Profit & Loss', 'accounting.profit-loss', 'bar-chart', $money($pl['net_profit']), 'Net profit this fiscal year · '.$money($plMonth['net_profit']).' this month'] : null,
        $user->can('accounting.reports.view') ? ['Balance Sheet', 'accounting.balance-sheet', 'layers', $money($sheet['total_assets']), 'Total assets · owes '.$money($sheet['total_liabilities'])] : null,
        $user->can('accounting.reports.view') ? ['Cash Flow', 'accounting.cash-flow', 'repeat', $money($cash), 'In cash, bank and mobile accounts now'] : null,
        $user->can('accounting.reports.view') ? ['Project Financials', 'accounting.projects', 'building', $money($pl['total_income']), 'Income this fiscal year, by project and unit'] : null,
        $user->can('accounting.reports.view') ? ['Receivables', 'accounting.receivables', 'users', $money($receivable), 'What each customer owes, less advances held'] : null,
        $user->can('accounting.reports.view') ? ['Payables', 'accounting.payables', 'briefcase', $money($payable), 'Owed to suppliers, contractors and others, before investors'] : null,
        $user->can('accounting.reports.view') ? ['Trial Balance', 'accounting.trial-balance', 'check-square', null, 'Every account with its debit or credit balance'] : null,
        $user->can('accounting.ledger.view') ? ['General Ledger', 'accounting.ledger', 'book', null, 'Every entry of one account, with a running balance'] : null,
    ]);
    $attentionLinks = [
        'negative' => ['Cash or bank accounts below zero', route('accounting.cash-bank.index')],
        'backlog' => ['Transactions waiting to be posted', Route::has('accounting.reconciliation.index') ? route('accounting.reconciliation.index') : route('accounting.settings.edit')],
        'journals' => ['Journals waiting for approval', route('accounting.journals.index', ['status' => 'submitted'])],
        'cheques' => ['Cheques not settled yet', route('accounting.cheques.index')],
    ];
@endphp
<x-layouts.app title="Accounting">
    <x-page-header title="Accounting" :breadcrumbs="['Dashboard' => route('dashboard'), 'Accounting' => null]">
        <x-slot:actions>
            @can('accounting.journal.create')<x-button variant="secondary" icon="plus" :href="route('accounting.journals.create')">New Journal</x-button>@endcan
            @can('accounting.cashbank.view')<x-button icon="wallet" :href="route('accounting.cash-bank.index')">Cash & Bank</x-button>@endcan
        </x-slot:actions>
    </x-page-header>

    @unless ($enabled)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-card border border-warning-500/30 bg-warning-50 px-5 py-4 text-sm text-warning-600">
            <span class="font-medium">The accounts are switched off</span>
            @can('accounting.settings.manage')<x-button size="sm" variant="secondary" :href="route('accounting.settings.edit')">Accounting Settings</x-button>@endcan
        </div>
    @endunless

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Cash & Bank" :value="$money($cash)" icon="wallet" tone="success" :href="$user->can('accounting.cashbank.view') ? route('accounting.cash-bank.index') : null" />
        <x-stat-card label="Customers Owe" :value="$money($receivable)" icon="users" tone="info" :href="$user->can('accounting.reports.view') ? route('accounting.receivables') : null" />
        <x-stat-card label="To Pay" :value="$money($payable)" icon="briefcase" tone="warning" :href="$user->can('accounting.reports.view') ? route('accounting.payables') : null" />
        <x-stat-card label="Net Profit This Fiscal Year" :value="$money($pl['net_profit'])" icon="bar-chart" :tone="App\Support\Money::cmp($pl['net_profit'], 0) < 0 ? 'danger' : 'accent'" :href="$user->can('accounting.reports.view') ? route('accounting.profit-loss') : null" />
    </div>

    <h2 class="mb-3 mt-8 text-[15px] font-semibold text-ink-900">Reports</h2>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as [$name, $route, $icon, $figure, $caption])
            <a href="{{ route($route) }}" class="card group flex items-start gap-4 p-5 transition hover:border-accent-500/50 hover:shadow-raised">
                <span class="icon-tile tone-accent shrink-0"><x-icon :name="$icon" size="h-5 w-5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center justify-between gap-2"><span class="text-[15px] font-semibold text-ink-900 group-hover:text-accent-700">{{ $name }}</span><x-icon name="arrow-right" size="h-4 w-4 text-ink-400 group-hover:text-accent-700" /></span>
                    @if ($figure)<span class="mt-1 block text-xl font-semibold tabular-nums text-ink-900">{{ $figure }}</span>@endif
                    <span class="mt-1 block text-[13px] text-ink-500">{{ $caption }}</span>
                </span>
            </a>
        @endforeach
    </div>

    @if ($attention)
        <h2 class="mb-3 mt-8 text-[15px] font-semibold text-ink-900">Needs Attention</h2>
        <x-card :padded="false">
            <ul class="divide-y divide-line">
                @foreach ($attention as $key => $count)
                    <li><a href="{{ $attentionLinks[$key][1] }}" class="flex items-center justify-between gap-3 px-5 py-3 text-sm hover:bg-surface-muted"><span class="font-medium text-ink-900">{{ $attentionLinks[$key][0] }}</span><x-badge tone="warning">{{ $count }}</x-badge></a></li>
                @endforeach
            </ul>
        </x-card>
    @endif
</x-layouts.app>
