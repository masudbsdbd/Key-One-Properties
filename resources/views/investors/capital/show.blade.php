@php $s = $tx->status; @endphp
<x-layouts.app :title="$tx->transaction_no">
    <x-page-header :title="$tx->transaction_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Capital' => route('investor-capital.index'), $tx->transaction_no => null]">
        <x-slot:actions>
            <x-document-menu :url="route('investor-capital.document', $tx)" />
            @can('investor.capital.approve')
                @if ($s === 'pending')
                    <x-button icon="check" data-action="post" data-url="{{ route('investor-capital.post', $tx) }}" data-reload data-confirm="Post {{ $tx->transaction_no }}?" data-confirm-message="{{ App\Models\InvestorCapitalTransaction::TYPES[$tx->transaction_type] }} · {{ App\Support\Format::money($tx->amount) }} · {{ $tx->investor->name }}" data-confirm-text="Post" data-tone="primary">Post</x-button>
                    <x-button variant="ghost" data-remote-modal="{{ route('investor-capital.reason', [$tx, 'reject']) }}" data-size="max-w-md">Reject</x-button>
                @endif
                @if ($s === 'posted')
                    <x-button variant="ghost" icon="restore" data-remote-modal="{{ route('investor-capital.reason', [$tx, 'reverse']) }}" data-size="max-w-md">Reverse</x-button>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($tx->status_reason)
        <div class="mb-6 rounded-card border border-danger-500/30 bg-danger-50 px-5 py-3 text-sm text-danger-600">{{ $tx->status_reason }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card class="min-w-0 xl:col-span-2">
            <div class="grid gap-5 sm:grid-cols-2">
                <div><p class="detail-label">Investor</p><a href="{{ route('investors.show', $tx->investor) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $tx->investor->name }}</a><p class="text-[13px] text-ink-500">{{ $tx->investor->investor_code }}</p></div>
                <div><p class="detail-label">Agreement</p><a href="{{ route('investor-agreements.show', $tx->agreement) }}" class="detail-value text-accent-700 hover:underline">{{ $tx->agreement->agreement_no }}</a><p class="text-[13px] text-ink-500">{{ $tx->project->code }} · {{ $tx->flat->building->code }} · Unit {{ $tx->flat->flat_no }}</p></div>
                <div><p class="detail-label">Type</p><x-badge :tone="$tx->transaction_type === 'contribution' ? 'success' : 'warning'">{{ App\Models\InvestorCapitalTransaction::TYPES[$tx->transaction_type] }}</x-badge></div>
                <div><p class="detail-label">Date</p><p class="detail-value">{{ App\Support\Format::date($tx->transaction_date) }}</p></div>
                <div><p class="detail-label">Method</p><p class="detail-value">{{ App\Modules\Investors\Controllers\CapitalController::METHODS[$tx->payment_method] ?? '—' }}</p></div>
                <div><p class="detail-label">Reference</p><p class="detail-value">{{ $tx->reference_no ?: '—' }}</p></div>
                <div><p class="detail-label">Recorded By</p><p class="detail-value">{{ $tx->creator->name }}</p></div>
                <div><p class="detail-label">Posted By</p><p class="detail-value">{{ $tx->approver?->name ?? '—' }}</p></div>
            </div>
            @if ($tx->notes)<p class="mt-4 whitespace-pre-line border-t border-line pt-4 text-sm text-ink-700">{{ $tx->notes }}</p>@endif
        </x-card>
        <x-card title="Amount">
            <p class="text-[28px] font-semibold text-ink-900"><x-money :value="$tx->amount" /></p>
            <div class="mt-3"><x-status-badge :status="$s" :labels="App\Models\InvestorCapitalTransaction::STATUSES" /></div>
        </x-card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-attachments :owner="$tx" type="investor-capital" />
        <x-card title="History" :padded="false">@include('projects.partials._activity', ['entries' => $activity])</x-card>
    </div>
</x-layouts.app>
