@forelse ($orders as $order)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('purchase-orders.show', $order) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $order->po_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($order->order_date) }}</p></td>
        @if ($showSupplier)<td class="text-ink-700">{{ $order->supplier->name }}</td>@endif
        <td class="text-ink-700">{{ $order->warehouse->code }}<span class="block text-[13px] text-ink-400">{{ $order->project?->code ?? 'Company' }}</span></td>
        <td class="text-right"><x-money :value="$order->total_amount" /></td>
        <td class="min-w-32">
            @php($pct = (float) $order->ordered_qty > 0 ? min(100, round((float) $order->received_qty / (float) $order->ordered_qty * 100)) : 0)
            <x-progress :value="$pct" size="h-1.5" />
        </td>
        <td><x-status-badge :status="$order->status" /></td>
    </tr>
@empty
    <tr><td colspan="{{ $showSupplier ? 6 : 5 }}"><x-empty-state icon="note" title="No purchase orders" /></td></tr>
@endforelse
