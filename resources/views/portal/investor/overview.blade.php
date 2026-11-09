@php
    $money = fn ($v) => App\Support\Format::money($v);
    $canProfit = $context->allows('investor.portal.profit.view');
@endphp
<x-layouts.portal title="Overview" portal="investor">
    <x-page-header :title="$context->investor->name" :breadcrumbs="['Overview' => null]" />

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Invested Capital" :value="$money($summary['capital'])" icon="tag" tone="accent" />
        <x-stat-card label="Committed (Active)" :value="$money($summary['committed'])" icon="file" tone="info" />
        <x-stat-card label="Active Investments" :value="$summary['active']" icon="door" tone="success" />
    </div>

    @if ($canProfit)
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-portal.figure label="Profit Paid" :value="$money($summary['paid'])" state="paid" />
            <x-portal.figure label="Approved, Not Yet Paid" :value="$money($summary['approved_due'])" state="approved" :meta="App\Support\Money::isPositive($summary['distribution_approved']) ? 'Payout approved: '.$money($summary['distribution_approved']) : null" />
            <x-portal.figure label="Waiting for Approval" :value="$money($summary['pending'])" state="pending" />
            <x-portal.figure label="Estimated Profit" :value="$money($summary['estimated'])" state="estimated" />
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <x-card title="Active Investments" :padded="false">
                <x-slot:actions>@if (Route::has('investor.investments.index'))<x-button size="sm" variant="secondary" href="{{ route('investor.investments.index') }}">View All</x-button>@endif</x-slot:actions>
                @forelse ($investments as $a)
                    <a href="{{ route('investor.investments.show', $a->public_id) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0 hover:bg-surface-muted">
                        <span class="icon-tile icon-tile-sm tone-accent"><x-icon name="door" size="h-4 w-4" /></span>
                        <span class="min-w-0 flex-1"><span class="block font-medium text-ink-900">{{ $a->project->name }} · Unit {{ $a->flat->flat_no }}</span><span class="block text-[13px] text-ink-500">{{ $a->agreement_no }} · since {{ App\Support\Format::date($a->effective_from) }}</span></span>
                        <span class="text-right"><span class="block font-semibold tabular-nums text-ink-900">{{ App\Modules\Investors\Controllers\AgreementController::pct($a->profit_share_percent) }}</span><x-money :value="$a->committed_capital" muted class="text-[13px]" /></span>
                    </a>
                @empty
                    <x-empty-state icon="briefcase" title="No active investments" />
                @endforelse
            </x-card>

            @if ($projects->isNotEmpty())
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($projects as $p)
                        @php $progress = $p->overallProgress(); @endphp
                        <a @if (Route::has('investor.projects.show')) href="{{ route('investor.projects.show', $p->public_id) }}" @endif class="card block p-5 hover:shadow-md">
                            <p class="font-semibold text-ink-900">{{ $p->name }}</p>
                            <p class="text-[13px] text-ink-500">{{ $p->code }}</p>
                            <p class="mt-4 text-sm text-ink-600">Construction</p>
                            <x-progress :value="$progress" class="mt-1" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="min-w-0 space-y-6">
            <x-card title="Recent Capital" :padded="false">
                @forelse ($recent as $t)
                    <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0">
                        <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-ink-900">{{ App\Models\InvestorCapitalTransaction::TYPES[$t->transaction_type] }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($t->transaction_date) }} · {{ $t->project->code }} Unit {{ $t->flat->flat_no }}</span></span>
                        <span class="text-right"><x-money :value="$t->amount" class="text-sm font-medium" /><span class="block"><x-status-badge :status="$t->status" :labels="App\Models\InvestorCapitalTransaction::STATUSES" /></span></span>
                    </div>
                @empty
                    <x-empty-state icon="tag" title="No capital entries" />
                @endforelse
            </x-card>
            @if ($canProfit)
                <x-card title="Recent Payouts" :padded="false">
                    @forelse ($payouts as $p)
                        <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0">
                            <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-ink-900">{{ $p->payout_no }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($p->paid_at) }}</span></span>
                            <span class="text-right"><x-money :value="$p->amount" class="text-sm font-medium" /><span class="block"><x-status-badge :status="$p->status" :labels="App\Models\InvestorPayout::STATUSES" /></span></span>
                        </div>
                    @empty
                        <x-empty-state icon="check-square" title="No payouts yet" />
                    @endforelse
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.portal>
