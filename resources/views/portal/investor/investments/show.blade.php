@php $pct = fn ($v) => App\Modules\Investors\Controllers\AgreementController::pct($v); @endphp
<x-layouts.portal :title="'Unit '.$flat->flat_no" portal="investor">
    <x-page-header :title="$agreement->project->name.' · Unit '.$flat->flat_no" :breadcrumbs="['Home' => route('investor.dashboard'), 'My Investments' => route('investor.investments.index'), 'Unit '.$flat->flat_no => null]">
        <x-slot:actions>@if ($canDocuments)<x-button variant="secondary" icon="download" href="{{ route('investor.documents.agreement', $agreement->public_id) }}?format=pdf">Agreement</x-button>@endif</x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Your Share of Profit" :value="$pct($agreement->profit_share_percent)" icon="bar-chart" tone="accent" />
        <x-stat-card label="Agreed Investment" :value="App\Support\Format::money($agreement->committed_capital)" icon="file" tone="info" />
        <x-stat-card label="You Invested" :value="App\Support\Format::money($position['balances']['capital'])" icon="tag" tone="success" />
        <x-stat-card label="Agreement" :value="App\Models\InvestorFlatAgreement::STATUSES[$agreement->status]" icon="check-square" tone="violet" />
    </div>

    @include('portal.investor._flat-financials')

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <x-card title="Money You Invested" :padded="false">
                @forelse ($capital as $t)
                    <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0">
                        <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-ink-900">{{ $t->transaction_type === 'contribution' ? 'You Invested' : 'Capital Returned to You' }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($t->transaction_date) }} · {{ $t->transaction_no }}</span></span>
                        @if ($t->status !== 'posted')<x-badge tone="warning">{{ ['pending' => 'Awaiting Confirmation', 'reversed' => 'Cancelled'][$t->status] ?? App\Models\InvestorCapitalTransaction::STATUSES[$t->status] }}</x-badge>@endif
                        <x-money :value="$t->transaction_type === 'capital_return' ? '-'.$t->amount : $t->amount" class="text-sm font-semibold" />
                        @if ($canDocuments && $t->status !== 'pending')<a href="{{ route('investor.documents.capital', $t->public_id) }}?format=pdf" class="btn btn-secondary btn-sm"><x-icon name="download" size="h-4 w-4" />Receipt</a>@endif
                    </div>
                @empty
                    <x-empty-state icon="tag" title="Nothing invested yet" />
                @endforelse
            </x-card>
            @if ($canProfit)
                <x-card title="Profit Paid to You" :padded="false">
                    @forelse ($payouts as $p)
                        <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0">
                            <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-ink-900">{{ App\Support\Format::date($p->paid_at) }} · {{ App\Modules\Investors\Controllers\CapitalController::METHODS[$p->payment_method] ?? $p->payment_method }}</span><span class="block text-[13px] text-ink-500">{{ $p->payout_no }}</span></span>
                            @if ($p->status === 'reversed')<x-badge tone="warning">Cancelled</x-badge>@endif
                            <x-money :value="$p->amount" class="text-sm font-semibold" />
                            @if ($canDocuments)<a href="{{ route('investor.documents.payout', $p->public_id) }}?format=pdf" class="btn btn-secondary btn-sm"><x-icon name="download" size="h-4 w-4" />Voucher</a>@endif
                        </div>
                    @empty
                        <x-empty-state icon="check-square" title="No profit paid yet" />
                    @endforelse
                </x-card>
            @endif
            @if ($canDocuments && $files->isNotEmpty())
                <x-card title="Files" :padded="false">
                    @foreach ($files as $f)
                        <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0">
                            <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink-900">{{ $f->original_name }}</span>
                            <a href="{{ route('investor.documents.file', $f->public_id) }}" class="btn btn-secondary btn-sm"><x-icon name="download" size="h-4 w-4" />Download</a>
                        </div>
                    @endforeach
                </x-card>
            @endif
        </div>
        <div class="min-w-0 space-y-6">
            <x-card title="About This Unit">
                <dl class="space-y-3 text-sm">
                    <div><dt class="detail-label">Project</dt><dd class="detail-value">{{ $agreement->project->name }}</dd></div>
                    <div><dt class="detail-label">Unit</dt><dd class="detail-value">{{ $flat->building->name }} · {{ $flat->floor->label }} · Unit {{ $flat->flat_no }}</dd></div>
                    <div><dt class="detail-label">Saleable Area</dt><dd class="detail-value">{{ number_format((float) $flat->saleable_area) }} {{ $flat->area_unit }}</dd></div>
                    <div><dt class="detail-label">Agreement</dt><dd class="detail-value">{{ $agreement->agreement_no }} · v{{ $agreement->version }}</dd></div>
                    <div><dt class="detail-label">{{ $agreement->effective_to ? 'Period' : 'Since' }}</dt><dd class="detail-value">{{ App\Support\Format::date($agreement->effective_from) }}{{ $agreement->effective_to ? ' – '.App\Support\Format::date($agreement->effective_to) : '' }}</dd></div>
                </dl>
                @if ($versions->count() > 1)
                    <div class="mt-4 border-t border-line pt-4">
                        <p class="detail-label">Versions</p>
                        @foreach ($versions as $v)
                            <a href="{{ route('investor.investments.show', $v->public_id) }}" @class(['mt-2 flex justify-between text-sm hover:text-accent-700', 'font-semibold text-ink-900' => $v->is($agreement), 'text-ink-600' => ! $v->is($agreement)])><span>v{{ $v->version }} · {{ $v->agreement_no }}</span><span>{{ $pct($v->profit_share_percent) }}</span></a>
                        @endforeach
                    </div>
                @endif
            </x-card>
            <x-card title="What Has Happened" :padded="false"><x-timeline :items="$timeline->map(fn ($i) => ['title' => $i['title'], 'meta' => App\Support\Format::date($i['at']).' · '.$i['meta'], 'tone' => $i['tone']])" /></x-card>
        </div>
    </div>
</x-layouts.portal>
