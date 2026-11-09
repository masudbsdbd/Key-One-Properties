@php $s = $d->status; @endphp
<x-layouts.app :title="$d->distribution_no">
    <x-page-header :title="$d->distribution_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Distributions' => route('distributions.index'), $d->distribution_no => null]">
        <x-slot:actions>
            @if ($s === 'pending_approval')
                @can('investor.profit.distribution.approve')
                    <x-button icon="check" data-action="post" data-url="{{ route('distributions.approve', $d) }}" data-reload data-confirm="Approve {{ $d->distribution_no }}?" data-confirm-message="{{ $d->investor->name }} · {{ App\Support\Format::money($d->amount) }}" data-confirm-text="Approve" data-tone="primary">Approve</x-button>
                @endcan
            @endif
            @if ($s === 'approved')
                @can('investor.profit.payout.post')<x-button icon="tag" data-remote-modal="{{ route('distributions.pay.form', $d) }}" data-size="max-w-lg">Pay</x-button>@endcan
            @endif
            @if (in_array($s, ['pending_approval', 'approved'], true))
                @can('investor.profit.distribution.approve')<x-button variant="ghost" data-remote-modal="{{ route('distributions.reject.form', $d) }}" data-size="max-w-md">Reject</x-button>@endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($d->status_reason)
        <div class="mb-6 rounded-card border border-danger-500/30 bg-danger-50 px-5 py-3 text-sm text-danger-600">{{ $d->status_reason }}</div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Amount" :value="App\Support\Format::money($d->amount)" icon="tag" tone="accent" />
        <x-stat-card label="Payable" :value="App\Support\Format::money($balances['payable'])" icon="bar-chart" tone="info" />
        <x-stat-card label="Paid" :value="App\Support\Format::money($balances['paid'])" icon="check" tone="success" />
        <x-stat-card label="Still Available" :value="App\Support\Format::money($available)" icon="layers" tone="violet" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card class="min-w-0 xl:col-span-2" title="Payouts" :padded="false">
            @forelse ($d->payouts as $p)
                <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0">
                    <span class="min-w-0 flex-1"><span class="block font-medium text-ink-900">{{ $p->payout_no }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($p->paid_at) }} · {{ App\Modules\Investors\Controllers\CapitalController::METHODS[$p->payment_method] ?? $p->payment_method }}{{ $p->payment_reference ? ' · '.$p->payment_reference : '' }} · {{ $p->poster->name }}</span></span>
                    <x-money :value="$p->amount" class="font-medium" />
                    <x-status-badge :status="$p->status" :labels="App\Models\InvestorPayout::STATUSES" />
                    <x-document-menu :url="route('payouts.document', $p)" />
                    @if ($p->status === 'posted')
                        @can('investor.profit.reverse')<button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('payouts.reverse.form', $p) }}" data-size="max-w-md" aria-label="Reverse {{ $p->payout_no }}"><x-icon name="restore" size="h-4 w-4" /></button>@endcan
                    @endif
                </div>
            @empty
                <x-empty-state icon="tag" title="No payouts" />
            @endforelse
        </x-card>
        <x-card title="Distribution">
            <dl class="space-y-3 text-sm">
                <div><dt class="detail-label">Investor</dt><dd><a href="{{ route('investors.show', $d->investor) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $d->investor->name }}</a></dd></div>
                <div><dt class="detail-label">Unit</dt><dd class="detail-value">{{ $d->flat->project->name }} · Unit {{ $d->flat->flat_no }}</dd></div>
                <div><dt class="detail-label">Status</dt><dd><x-status-badge :status="$s" :labels="App\Models\InvestorProfitDistribution::STATUSES" /></dd></div>
                <div><dt class="detail-label">Requested</dt><dd class="detail-value">{{ $d->requester->name }} · {{ App\Support\Format::date($d->created_at) }}</dd></div>
                <div><dt class="detail-label">Approved</dt><dd class="detail-value">{{ $d->approver && $s !== 'rejected' ? $d->approver->name.' · '.App\Support\Format::date($d->approved_at) : '—' }}</dd></div>
            </dl>
        </x-card>
    </div>
</x-layouts.app>
