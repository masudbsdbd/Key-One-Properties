@php
    $user = auth()->user();
    $s = $order->status;
    $canEdit = Gate::allows('po:edit', $order);
    $canApprove = Gate::allows('po:approve', $order);
    $receivable = in_array($s, App\Models\PurchaseOrder::RECEIVABLE, true);
    $history = collect([
        ['title' => 'Created', 'meta' => $order->creator->name.' · '.App\Support\Format::datetime($order->created_at), 'tone' => 'info'],
        $order->submitted_at ? ['title' => 'Submitted', 'meta' => App\Support\Format::datetime($order->submitted_at), 'tone' => 'info'] : null,
        $order->approved_at && $s !== 'rejected' ? ['title' => 'Approved', 'meta' => $order->approver?->name.' · '.App\Support\Format::datetime($order->approved_at), 'tone' => 'success'] : null,
        $s === 'rejected' ? ['title' => 'Rejected', 'meta' => $order->approver?->name.' · '.App\Support\Format::datetime($order->updated_at), 'body' => $order->rejection_reason, 'tone' => 'danger'] : null,
    ])->filter()
        ->merge($order->receipts->map(fn ($r) => ['title' => 'Received · '.$r->grn_no, 'meta' => $r->receiver->name.' · '.App\Support\Format::date($r->received_at), 'body' => $r->lines->map(fn ($l) => $l->material->name.': '.App\Support\Format::qty($l->accepted_qty))->join(', '), 'tone' => 'accent']))
        ->merge($returns->map(fn ($r) => ['title' => 'Returned · '.$r->return_no, 'meta' => App\Support\Format::datetime($r->posted_at), 'body' => $r->reason, 'tone' => 'warning']))
        ->when(in_array($s, ['cancelled', 'closed'], true), fn ($c) => $c->push(['title' => App\Models\PurchaseOrder::STATUSES[$s], 'meta' => App\Support\Format::datetime($order->updated_at), 'body' => $order->rejection_reason, 'tone' => 'neutral']))
        ->values()->all();
@endphp
<x-layouts.app :title="$order->po_no">
    <x-page-header :title="$order->po_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Purchase Orders' => route('purchase-orders.index'), $order->po_no => null]">
        <x-slot:actions>
            <x-document-menu :url="route('purchase-orders.document', $order)" />
            @if ($receivable && Gate::allows('po:receive', $order))
                <x-button icon="download" data-remote-modal="{{ route('purchase-orders.receive.form', $order) }}" data-size="max-w-4xl">Receive Goods</x-button>
            @endif
            @if ($s === 'submitted' && $canApprove)
                <x-button icon="check" data-action="post" data-url="{{ route('purchase-orders.approve', $order) }}" data-reload data-confirm="Approve {{ $order->po_no }}?" data-confirm-text="Approve" data-tone="primary">Approve</x-button>
                <x-button variant="ghost" data-remote-modal="{{ route('purchase-orders.reason', [$order, 'reject']) }}" data-size="max-w-md">Reject</x-button>
            @endif
            @if ($s === 'draft' && $canEdit)
                <x-button icon="arrow-right" data-action="post" data-url="{{ route('purchase-orders.submit', $order) }}" data-reload data-confirm="Submit {{ $order->po_no }} for approval?" data-confirm-text="Submit" data-tone="primary">Submit</x-button>
                <x-button variant="secondary" icon="edit" data-remote-modal="{{ route('purchase-orders.edit', $order) }}" data-size="max-w-5xl">Edit</x-button>
            @endif
            @if ($s === 'partially_received' && $canApprove)
                <x-button variant="secondary" data-action="post" data-url="{{ route('purchase-orders.close', $order) }}" data-reload data-confirm="Close {{ $order->po_no }}?" data-confirm-message="Remaining quantities will not be received." data-confirm-text="Close">Close</x-button>
            @endif
            @if (in_array($s, ['draft', 'submitted', 'approved'], true) && $canEdit && $order->receipts->isEmpty())
                <x-button variant="ghost" icon="x" data-remote-modal="{{ route('purchase-orders.reason', [$order, 'cancel']) }}" data-size="max-w-md" aria-label="Cancel order" />
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <x-card>
                <div class="grid gap-5 sm:grid-cols-4">
                    <div class="sm:col-span-2"><p class="detail-label">Supplier</p><a href="{{ route('suppliers.show', $order->supplier) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $order->supplier->name }}</a><p class="text-[13px] text-ink-500">{{ $order->supplier->supplier_code }}</p></div>
                    <div class="sm:col-span-2"><p class="detail-label">Deliver To</p><p class="detail-value font-semibold">{{ $order->warehouse->name }}</p><p class="text-[13px] text-ink-500">{{ $order->project?->name ?? 'Company' }}</p></div>
                    <div><p class="detail-label">Order Date</p><p class="detail-value">{{ App\Support\Format::date($order->order_date) }}</p></div>
                    <div><p class="detail-label">Expected</p><p class="detail-value">{{ App\Support\Format::date($order->expected_date) }}</p></div>
                    <div><p class="detail-label">Status</p><x-status-badge :status="$s" /></div>
                    <div><p class="detail-label">Total</p><p class="detail-value font-semibold"><x-money :value="$order->total_amount" /></p></div>
                </div>
                @if ($order->notes)<p class="mt-4 border-t border-line pt-4 text-sm text-ink-700">{{ $order->notes }}</p>@endif
            </x-card>

            <x-card title="Items" :padded="false">
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Material</th><th class="text-right">Ordered</th><th class="text-right">Received</th><th class="text-right">Unit Price</th><th class="text-right">Total</th></tr></thead>
                        <tbody>
                            @foreach ($order->lines as $line)
                                <tr>
                                    <td class="min-w-48"><p class="font-medium text-ink-900">{{ $line->material->name }}</p><p class="text-[13px] text-ink-500">{{ $line->material->sku }} · {{ $line->material->unit->code }}</p></td>
                                    <td class="text-right tabular-nums">{{ App\Support\Format::qty($line->quantity) }}</td>
                                    <td class="text-right tabular-nums">{{ App\Support\Format::qty($line->received_qty) }}@if ($receivable && App\Support\Money::isPositive($line->remaining()))<span class="block text-[13px] text-warning-600">{{ App\Support\Format::qty($line->remaining()) }} left</span>@endif</td>
                                    <td class="text-right"><x-money :value="$line->unit_price" /></td>
                                    <td class="text-right">
                                        <x-money :value="$line->line_total" class="font-medium" />
                                        @if ((float) $line->discount_amount)<span class="block text-[13px] text-ink-500">Discount <x-money :value="$line->discount_amount" /></span>@endif
                                        @if ((float) $line->tax_amount)<span class="block text-[13px] text-ink-500">Tax <x-money :value="$line->tax_amount" /></span>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-line text-sm">
                            <tr><td colspan="4" class="px-5 pt-3 pb-1 text-right text-ink-500">Subtotal</td><td class="px-5 pt-3 pb-1 text-right"><x-money :value="$order->subtotal" /></td></tr>
                            @if ((float) $order->discount_amount)<tr><td colspan="4" class="px-5 py-1 text-right text-ink-500">Discount</td><td class="px-5 py-1 text-right"><x-money :value="'-'.$order->discount_amount" muted /></td></tr>@endif
                            @if ((float) $order->tax_amount)<tr><td colspan="4" class="px-5 py-1 text-right text-ink-500">Tax</td><td class="px-5 py-1 text-right"><x-money :value="$order->tax_amount" muted /></td></tr>@endif
                            <tr><td colspan="4" class="px-5 pt-1 pb-3 text-right font-semibold text-ink-900">Total</td><td class="px-5 pt-1 pb-3 text-right font-semibold"><x-money :value="$order->total_amount" /></td></tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>

            <x-card title="Goods Receipts" :padded="false">
                @forelse ($order->receipts as $receipt)
                    <div class="border-b border-line px-5 py-4 last:border-0">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div><p class="font-semibold text-ink-900">{{ $receipt->grn_no }}</p><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($receipt->received_at) }} · {{ $receipt->receiver->name }}@if ($receipt->supplier_challan_no) · Challan {{ $receipt->supplier_challan_no }}@endif</p></div>
                            <div class="flex items-center gap-2">
                            <x-document-menu compact :url="route('purchase-orders.receipt.document', [$order, $receipt])" />
                            @if (Gate::allows('po:return', $order) && $receipt->lines->contains(fn ($l) => App\Support\Money::isPositive($l->returnable())))
                                <x-button size="sm" variant="secondary" icon="restore" data-remote-modal="{{ route('purchase-orders.return.form', [$order, $receipt]) }}" data-size="max-w-3xl">Return</x-button>
                            @endif
                            </div>
                        </div>
                        <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                            @foreach ($receipt->lines as $line)
                                <li class="flex items-center justify-between rounded-control bg-surface-muted px-3 py-2">
                                    <span class="text-ink-700">{{ $line->material->name }}</span>
                                    <span class="tabular-nums text-ink-900">{{ App\Support\Format::qty($line->accepted_qty) }} {{ $line->material->unit->code }}
                                        @if ((float) $line->rejected_qty)<span class="text-danger-600" data-tooltip="Rejected">· {{ App\Support\Format::qty($line->rejected_qty) }}</span>@endif
                                        @if ((float) $line->returned_qty)<span class="text-warning-600" data-tooltip="Returned">· {{ App\Support\Format::qty($line->returned_qty) }}</span>@endif
                                        @if ($line->billed_bill_id)<x-badge tone="violet" class="ml-1">Billed</x-badge>@endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <x-empty-state icon="download" title="Nothing received yet" />
                @endforelse
            </x-card>

            @if ($returns->isNotEmpty())
                <x-card title="Returns" :padded="false">
                    <ul class="divide-y divide-line">
                        @foreach ($returns as $return)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <span class="font-medium text-ink-900">{{ $return->return_no }}</span>
                                <span class="min-w-0 flex-1 truncate text-sm text-ink-600">{{ $return->reason }}</span>
                                <x-money :value="$return->total_amount" />
                                <x-document-menu compact :url="route('purchase-orders.return.document', [$order, $return])" />
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($bills->isNotEmpty())
                <x-card title="Bills" :padded="false">
                    <ul class="divide-y divide-line">
                        @foreach ($bills as $bill)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <a href="{{ route('supplier-bills.show', $bill) }}" class="font-medium text-accent-700 hover:underline">{{ $bill->bill_no }}</a>
                                <span class="text-sm text-ink-600">{{ App\Support\Format::date($bill->invoice_date) }}</span>
                                <x-money :value="$bill->total_amount" />
                                <x-status-badge :status="$bill->status" />
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-attachments :owner="$order" type="purchase-order" />
        </div>

        <x-card title="History" :padded="false"><x-timeline :items="$history" /></x-card>
    </div>
</x-layouts.app>
