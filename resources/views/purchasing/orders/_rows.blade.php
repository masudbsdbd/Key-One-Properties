@php
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.') ?: '0';
    $inProject ??= false;
@endphp
@forelse ($orders as $order)
    @php
        $pct = (float) $order->ordered_qty > 0 ? min(100, round((float) $order->received_qty / (float) $order->ordered_qty * 100)) : 0;
        $waiting = in_array($order->status, App\Models\PurchaseOrder::RECEIVABLE, true) && $order->expected_date;
        $late = $waiting && App\Support\Dates::daysUntil($order->expected_date) < 0;
        $due = App\Support\Money::sub($order->billed ?? '0', $order->paid ?? '0');
    @endphp
    <tr>
        <td class="whitespace-nowrap">
            <a href="{{ route('purchase-orders.show', $order) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $order->po_no }}</a>
            <p class="text-[13px] text-ink-500">{{ App\Support\Format::date($order->order_date) }}</p>
            @if ($waiting)<p @class(['text-[12px]', 'font-medium text-danger-600' => $late, 'text-ink-500' => ! $late])>Expected {{ App\Support\Format::date($order->expected_date) }} · {{ App\Support\Dates::countdown($order->expected_date) }}</p>@endif
        </td>
        @if ($showSupplier)<td class="min-w-36 text-ink-700"><x-ref :to="$order->supplier">{{ $order->supplier->name }}</x-ref></td>@endif
        <td class="min-w-0 max-w-64">
            @foreach ($order->lines->take(2) as $line)<span class="block truncate text-[13px] text-ink-700">{{ $line->material?->name }} <span class="tabular-nums text-ink-500">{{ $qty($line->quantity) }} {{ $line->material?->unit?->code }}</span></span>@endforeach
            @if ($order->lines->count() > 2)<span class="block text-[12px] text-ink-500">+{{ $order->lines->count() - 2 }} more</span>@endif
            <span class="block truncate text-[12px] text-ink-500">To {{ $order->warehouse->name }}@unless ($inProject) · <x-ref :to="$order->project">{{ $order->project?->code ?? 'Company' }}</x-ref>@endunless</span>
        </td>
        <td class="text-right"><x-money :value="$order->total_amount" /></td>
        <td class="min-w-28"><x-progress :value="$pct" size="h-1.5" /></td>
        <td class="whitespace-nowrap">
            @if (App\Support\Money::isPositive($order->not_billed ?? '0'))
                <x-badge tone="warning">Not Billed</x-badge><span class="block text-[12px] text-ink-500">{{ App\Support\Format::money($order->not_billed) }} received</span>
            @endif
            @if (App\Support\Money::isPositive($order->billed ?? '0'))
                @if (App\Support\Money::isPositive($due))
                    <x-badge tone="warning">{{ App\Support\Money::isPositive($order->paid) ? 'Partly Paid' : 'Unpaid' }}</x-badge><span class="block text-[12px] text-ink-500">{{ App\Support\Format::money($due) }} due</span>
                @else
                    <x-badge tone="success">Paid</x-badge><span class="block text-[12px] text-ink-500">{{ App\Support\Format::money($order->billed) }}</span>
                @endif
            @elseif (! App\Support\Money::isPositive($order->not_billed ?? '0'))
                <span class="text-ink-400">—</span>
            @endif
        </td>
        <td class="whitespace-nowrap"><x-status-badge :status="$order->status" /></td>
    </tr>
@empty
    <tr><td colspan="{{ $showSupplier ? 7 : 6 }}"><x-empty-state icon="note" title="No purchase orders" /></td></tr>
@endforelse
