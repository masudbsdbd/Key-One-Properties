@php
    $history = collect([
        ['title' => 'Recorded', 'meta' => $payment->creator->name.' · '.App\Support\Format::datetime($payment->created_at), 'tone' => 'info'],
        $payment->posted_at ? ['title' => 'Approved', 'meta' => App\Support\Format::datetime($payment->posted_at), 'tone' => 'success'] : null,
        $payment->status === 'rejected' ? ['title' => 'Rejected', 'meta' => App\Support\Format::datetime($payment->updated_at), 'body' => $payment->reversal_reason, 'tone' => 'danger'] : null,
        $payment->reversed_at ? ['title' => 'Reversed', 'meta' => App\Support\Format::datetime($payment->reversed_at), 'body' => $payment->reversal_reason, 'tone' => 'danger'] : null,
    ])->filter()->values()->all();
@endphp
<x-layouts.app :title="$payment->payment_no">
    <x-page-header :title="$payment->payment_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Contractor Payments' => route('contractor-payments.index'), $payment->payment_no => null]">
        @can('cpay:approve', $payment)
            <x-slot:actions>
                @if ($payment->status === 'pending')
                    <x-button icon="check" data-action="post" data-url="{{ route('contractor-payments.approve', $payment) }}" data-reload data-confirm="Approve {{ $payment->payment_no }}?" data-confirm-message="{{ App\Support\Format::money($payment->amount) }} to {{ $payment->contractor->name }}." data-confirm-text="Approve" data-tone="primary">Approve</x-button>
                    <x-button variant="ghost" data-remote-modal="{{ route('contractor-payments.reason', [$payment, 'reject']) }}" data-size="max-w-md">Reject</x-button>
                @elseif ($payment->status === 'approved')
                    <x-button variant="ghost" icon="restore" data-remote-modal="{{ route('contractor-payments.reason', [$payment, 'reverse']) }}" data-size="max-w-md">Reverse</x-button>
                @endif
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <x-card>
                <div class="grid gap-5 sm:grid-cols-4">
                    <div class="sm:col-span-2"><p class="detail-label">Contractor</p><a href="{{ route('contractors.show', $payment->contractor) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $payment->contractor->name }}</a><p class="text-[13px] text-ink-500">{{ $payment->contractor->contractor_code }}</p></div>
                    <div><p class="detail-label">Amount</p><p class="detail-value text-lg font-semibold"><x-money :value="$payment->amount" /></p></div>
                    <div><p class="detail-label">Status</p><x-status-badge :status="$payment->status" /></div>
                    <div class="sm:col-span-2"><p class="detail-label">Against</p>
                        @if ($payment->bill)<a href="{{ route('contractor-bills.show', $payment->bill) }}" class="detail-value text-accent-700 hover:underline">{{ $payment->bill->bill_no }}</a>@else<x-badge tone="info">Advance</x-badge>@endif
                        <p class="text-[13px] text-ink-500"><a href="{{ route('work-orders.show', $payment->workOrder) }}" class="hover:text-accent-700">{{ $payment->workOrder->wo_no }}</a> · {{ $payment->project->code }}</p>
                    </div>
                    <div><p class="detail-label">Date</p><p class="detail-value">{{ App\Support\Format::date($payment->payment_date) }}</p></div>
                    <div><p class="detail-label">Method</p><p class="detail-value">{{ App\Models\ContractorPayment::METHODS[$payment->payment_method] }}</p><p class="text-[13px] text-ink-500">{{ $payment->reference }}</p></div>
                </div>
                @if ($payment->notes)<p class="mt-4 border-t border-line pt-4 text-sm text-ink-700">{{ $payment->notes }}</p>@endif
            </x-card>
            <x-attachments :owner="$payment" type="contractor-payment" />
        </div>
        <x-card title="History" :padded="false"><x-timeline :items="$history" /></x-card>
    </div>
</x-layouts.app>
