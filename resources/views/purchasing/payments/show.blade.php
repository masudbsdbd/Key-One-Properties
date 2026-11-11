@php
    $history = collect([
        ['title' => 'Recorded', 'meta' => $payment->creator->name.' · '.App\Support\Format::datetime($payment->created_at), 'tone' => 'info'],
        $payment->approved_at ? ['title' => $payment->status === 'rejected' ? 'Rejected' : 'Approved', 'meta' => App\Support\Format::datetime($payment->approved_at), 'body' => $payment->status === 'rejected' ? $payment->reversal_reason : null, 'tone' => $payment->status === 'rejected' ? 'danger' : 'success'] : null,
        $payment->reversed_at ? ['title' => 'Reversed', 'meta' => App\Support\Format::datetime($payment->reversed_at), 'body' => $payment->reversal_reason, 'tone' => 'danger'] : null,
    ])->filter()->values()->all();
    $allocated = App\Support\Money::add('0', ...$payment->allocations->map(fn ($a) => (string) $a->amount)->all());
@endphp
<x-layouts.app :title="$payment->payment_no">
    <x-page-header :title="$payment->payment_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Supplier Payments' => route('supplier-payments.index'), $payment->payment_no => null]">
        <x-slot:actions>
            <x-document-menu :url="route('supplier-payments.document', $payment)" />
            @can('purchases.payment.approve')
                @if ($payment->status === 'pending')
                    <x-button icon="check" data-action="post" data-url="{{ route('supplier-payments.approve', $payment) }}" data-reload data-confirm="Approve {{ $payment->payment_no }}?" data-confirm-message="{{ App\Support\Format::money($payment->amount) }} to {{ $payment->supplier->name }}." data-confirm-text="Approve" data-tone="primary">Approve</x-button>
                    <x-button variant="ghost" data-remote-modal="{{ route('supplier-payments.reason', [$payment, 'reject']) }}" data-size="max-w-md">Reject</x-button>
                @elseif ($payment->status === 'approved')
                    <x-button variant="ghost" icon="restore" data-remote-modal="{{ route('supplier-payments.reason', [$payment, 'reverse']) }}" data-size="max-w-md">Reverse</x-button>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <x-card>
                <div class="grid gap-5 sm:grid-cols-4">
                    <div class="sm:col-span-2"><p class="detail-label">Supplier</p><a href="{{ route('suppliers.show', $payment->supplier) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $payment->supplier->name }}</a><p class="text-[13px] text-ink-500">{{ $payment->supplier->supplier_code }}</p></div>
                    <div><p class="detail-label">Amount</p><p class="detail-value text-lg font-semibold"><x-money :value="$payment->amount" /></p></div>
                    <div><p class="detail-label">Status</p><x-status-badge :status="$payment->status" /></div>
                    <div><p class="detail-label">Date</p><p class="detail-value">{{ App\Support\Format::date($payment->payment_date) }}</p></div>
                    <div><p class="detail-label">Method</p><p class="detail-value">{{ App\Models\SupplierPayment::METHODS[$payment->payment_method] }}</p>@if ($payment->moneyAccount)<p class="text-[13px] text-ink-500">{{ $payment->moneyAccount->label() }}</p>@endif</div>
                    @if ((float) $payment->tax_deducted)<div><p class="detail-label">Tax Deducted</p><p class="detail-value"><x-money :value="$payment->tax_deducted" /></p><p class="text-[13px] text-ink-500">Paid out <x-money :value="App\Support\Money::sub((string) $payment->amount, (string) $payment->tax_deducted)" /></p></div>@endif
                    <div><p class="detail-label">Reference</p><p class="detail-value">{{ $payment->payment_reference ?: '—' }}</p></div>
                    <div><p class="detail-label">Advance</p><p class="detail-value"><x-money :value="App\Support\Money::sub((string) $payment->amount, $allocated)" /></p></div>
                </div>
                @if ($payment->notes)<p class="mt-4 border-t border-line pt-4 text-sm text-ink-700">{{ $payment->notes }}</p>@endif
            </x-card>

            <x-card title="Allocations" :padded="false">
                @if ($payment->allocations->isEmpty())
                    <x-empty-state icon="file" title="Not allocated" />
                @else
                    <table class="data-table">
                        <thead><tr><th>Bill</th><th>Invoice Date</th><th class="text-right">Bill Total</th><th class="text-right">Allocated</th></tr></thead>
                        <tbody>
                            @foreach ($payment->allocations as $allocation)
                                <tr>
                                    <td><a href="{{ route('supplier-bills.show', $allocation->bill) }}" class="font-medium text-accent-700 hover:underline">{{ $allocation->bill->bill_no }}</a></td>
                                    <td>{{ App\Support\Format::date($allocation->bill->invoice_date) }}</td>
                                    <td class="text-right"><x-money :value="$allocation->bill->total_amount" /></td>
                                    <td class="text-right font-medium"><x-money :value="$allocation->amount" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            <x-attachments :owner="$payment" type="supplier-payment" />
        </div>
        <x-card title="History" :padded="false"><x-timeline :items="$history" /></x-card>
    </div>
</x-layouts.app>
