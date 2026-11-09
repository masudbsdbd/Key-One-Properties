@php
    $s = $payment->status;
    $doc = $payment->sale ?? $payment->booking;
    $history = collect([
        ['title' => 'Received', 'meta' => $payment->receiver->name.' · '.App\Support\Format::datetime($payment->created_at), 'tone' => 'info'],
        $payment->posted_at ? ['title' => 'Posted · '.$payment->receipt?->receipt_no, 'meta' => $payment->approver?->name.' · '.App\Support\Format::datetime($payment->posted_at), 'tone' => 'success'] : null,
        $s === 'rejected' ? ['title' => 'Rejected', 'meta' => $payment->approver?->name, 'body' => $payment->status_reason, 'tone' => 'danger'] : null,
        $payment->reversed_at ? ['title' => 'Reversed', 'meta' => App\Support\Format::datetime($payment->reversed_at), 'body' => $payment->status_reason, 'tone' => 'danger'] : null,
    ])->filter()->values()->all();
@endphp
<x-layouts.app :title="$payment->payment_no">
    <x-page-header :title="$payment->payment_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Payments' => route('customer-payments.index'), $payment->payment_no => null]">
        <x-slot:actions>
            @if ($payment->receipt)@can('collections.payment.receipt.view')<x-document-menu :url="route('customer-payments.receipt', $payment)" />@endcan @endif
            @if ($s === 'pending')
                @can('collections.payment.approve')
                    <x-button icon="check" data-action="post" data-url="{{ route('customer-payments.post', $payment) }}" data-reload data-confirm="Post {{ $payment->payment_no }}?" data-confirm-message="{{ App\Support\Format::money($payment->amount) }} from {{ $payment->customer->full_name }}. A money receipt is issued." data-confirm-text="Post" data-tone="primary">Post</x-button>
                    <x-button variant="ghost" data-remote-modal="{{ route('customer-payments.reason', [$payment, 'reject']) }}" data-size="max-w-md">Reject</x-button>
                @endcan
            @endif
            @if ($s === 'posted')
                @can('collections.payment.reverse')<x-button variant="ghost" icon="restore" data-remote-modal="{{ route('customer-payments.reason', [$payment, 'reverse']) }}" data-size="max-w-md">Reverse</x-button>@endcan
            @endif
        </x-slot:actions>
    </x-page-header>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <x-card>
                <div class="grid gap-5 sm:grid-cols-3">
                    <div class="sm:col-span-2"><p class="detail-label">Customer</p><a href="{{ route('customers.statement', $payment->customer) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $payment->customer->full_name }}</a><p class="text-[13px] text-ink-500"><x-ref :to="$payment->project">{{ $payment->project->name }}</x-ref> · <x-ref :to="$doc->flat">Unit {{ $doc->flat->flat_no }}</x-ref> · <a href="{{ $payment->sale ? route('sales.show', $payment->sale) : route('bookings.show', $payment->booking) }}" class="text-accent-700 hover:underline">{{ $payment->sale?->sale_no ?? $payment->booking?->booking_no }}</a></p></div>
                    <div><p class="detail-label">Amount</p><p class="text-xl font-semibold text-ink-900"><x-money :value="$payment->amount" /></p><x-status-badge :status="$s" /></div>
                    <div><p class="detail-label">Date</p><p class="detail-value">{{ App\Support\Format::date($payment->payment_date) }}</p></div>
                    <div><p class="detail-label">Method</p><p class="detail-value">{{ App\Models\CustomerPayment::METHODS[$payment->method] ?? $payment->method }}</p><p class="text-[13px] text-ink-500">{{ $payment->reference_no }}</p></div>
                    <div><p class="detail-label">Receipt</p><p class="detail-value">{{ $payment->receipt?->receipt_no ?? '—' }}@if ($payment->receipt?->voided_at) <x-badge tone="danger">Void</x-badge>@endif</p></div>
                </div>
                @if ($payment->notes)<p class="mt-4 border-t border-line pt-4 text-sm text-ink-700">{{ $payment->notes }}</p>@endif
            </x-card>
            @if ($payment->allocations->isNotEmpty())
                <x-card title="Applied To" :padded="false">
                    <table class="data-table">
                        <tbody>
                            @foreach ($payment->allocations as $a)
                                <tr @class(['text-ink-400' => $a->reversal_of_id])>
                                    <td>{{ App\Models\PaymentAllocation::TYPES[$a->allocation_type] }}@if ($a->item) · #{{ $a->item->sequence_no }} · due {{ App\Support\Format::date($a->item->due_date) }}@endif @if ($a->reversal_of_id)<span class="text-[13px]">(reversal)</span>@endif</td>
                                    <td class="text-right"><x-money :value="$a->amount" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endif
        </div>
        <x-card title="History" :padded="false"><x-timeline :items="$history" /></x-card>
    </div>
</x-layouts.app>
