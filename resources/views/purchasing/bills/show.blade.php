@php
    $user = auth()->user();
    $history = collect([
        ['title' => 'Recorded', 'meta' => $bill->creator->name.' · '.App\Support\Format::datetime($bill->created_at), 'tone' => 'info'],
        $bill->approved_at ? ['title' => 'Approved', 'meta' => App\Support\Format::datetime($bill->approved_at), 'tone' => 'success'] : null,
    ])->filter()
        ->merge($bill->allocations->map(fn ($a) => ['title' => 'Payment · '.$a->payment->payment_no, 'meta' => App\Support\Format::date($a->payment->payment_date).' · '.App\Support\Format::money($a->amount), 'body' => App\Models\SupplierPayment::STATUSES[$a->payment->status], 'tone' => $a->payment->status === 'approved' ? 'success' : 'neutral']))
        ->when($bill->reversed_at, fn ($c) => $c->push(['title' => 'Reversed', 'meta' => App\Support\Format::datetime($bill->reversed_at), 'body' => $bill->reversal_reason, 'tone' => 'danger']))
        ->values()->all();
@endphp
<x-layouts.app :title="$bill->bill_no">
    <x-page-header :title="$bill->bill_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Supplier Bills' => route('supplier-bills.index'), $bill->bill_no => null]">
        <x-slot:actions>
            <x-document-menu :url="route('supplier-bills.document', $bill)" />
            @if ($bill->status === 'draft')
                @can('purchases.bill.approve')<x-button icon="check" data-action="post" data-url="{{ route('supplier-bills.approve', $bill) }}" data-reload data-confirm="Approve {{ $bill->bill_no }}?" data-confirm-message="{{ App\Support\Format::money($bill->total_amount) }} will be added to {{ $bill->supplier->name }}'s payable." data-confirm-text="Approve" data-tone="primary">Approve</x-button>@endcan
                @can('purchases.bill.manage')<x-button variant="ghost" icon="trash" data-action="post" data-url="{{ route('supplier-bills.cancel', $bill) }}" data-reload data-confirm="Discard draft {{ $bill->bill_no }}?" data-confirm-text="Discard" aria-label="Discard draft" />@endcan
            @endif
            @if ($bill->status === 'approved')
                @can('purchases.payment.create')<x-button icon="tag" data-remote-modal="{{ route('supplier-payments.create', ['supplier' => $bill->supplier->public_id]) }}" data-size="max-w-3xl">Pay</x-button>@endcan
                @if (! App\Support\Money::isPositive($paid))
                    @can('purchases.bill.approve')<x-button variant="ghost" icon="restore" data-remote-modal="{{ route('supplier-bills.reverse.form', $bill) }}" data-size="max-w-md">Reverse</x-button>@endcan
                @endif
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-journal-links :source="$bill" />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <x-card>
                <div class="grid gap-5 sm:grid-cols-4">
                    <div class="sm:col-span-2"><p class="detail-label">Supplier</p><a href="{{ route('suppliers.show', $bill->supplier) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $bill->supplier->name }}</a><p class="text-[13px] text-ink-500">{{ $bill->supplier_invoice_no ? 'Invoice '.$bill->supplier_invoice_no : $bill->supplier->supplier_code }}</p></div>
                    <div><p class="detail-label">Purchase Order</p>@if ($bill->purchaseOrder)<a href="{{ route('purchase-orders.show', $bill->purchaseOrder) }}" class="detail-value text-accent-700 hover:underline">{{ $bill->purchaseOrder->po_no }}</a>@else<p class="detail-value">—</p>@endif</div>
                    <div><p class="detail-label">Status</p><x-status-badge :status="$bill->status" /></div>
                    <div><p class="detail-label">Invoice Date</p><p class="detail-value">{{ App\Support\Format::date($bill->invoice_date) }}</p></div>
                    <div><p class="detail-label">Due Date</p><p class="detail-value">{{ App\Support\Format::date($bill->due_date) }}</p></div>
                    <div><p class="detail-label">Paid</p><p class="detail-value"><x-money :value="$paid" /></p></div>
                    <div><p class="detail-label">Outstanding</p><p class="detail-value font-semibold"><x-money :value="in_array($bill->status, ['approved', 'paid'], true) ? $outstanding : 0" /></p></div>
                </div>
                @if ($bill->notes)<p class="mt-4 border-t border-line pt-4 text-sm text-ink-700">{{ $bill->notes }}</p>@endif
            </x-card>

            <x-card title="Lines" :padded="false">
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Description</th><th class="text-right">Qty</th><th class="text-right">Unit Price</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($bill->lines as $line)
                                <tr>
                                    <td class="text-ink-900">{{ $line->description }}</td>
                                    <td class="text-right tabular-nums">{{ App\Support\Format::qty($line->quantity) }}</td>
                                    <td class="text-right">@if ($line->unit_price !== null)<x-money :value="$line->unit_price" />@else<span class="text-ink-400">—</span>@endif</td>
                                    <td class="text-right font-medium"><x-money :value="$line->amount" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-line text-sm">
                            @if ((float) $bill->tax_amount)
                                <tr><td colspan="3" class="px-5 py-2 text-right text-ink-500">Subtotal</td><td class="px-5 py-2 text-right"><x-money :value="$bill->subtotal" /></td></tr>
                                <tr><td colspan="3" class="px-5 py-2 text-right text-ink-500">VAT / Tax</td><td class="px-5 py-2 text-right"><x-money :value="$bill->tax_amount" muted /></td></tr>
                            @endif
                            <tr><td colspan="3" class="px-5 py-2 text-right font-semibold text-ink-900">Total</td><td class="px-5 py-2 text-right font-semibold"><x-money :value="$bill->total_amount" /></td></tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>

            <x-attachments :owner="$bill" type="supplier-bill" />
        </div>
        <x-card title="History" :padded="false"><x-timeline :items="$history" /></x-card>
    </div>
</x-layouts.app>
