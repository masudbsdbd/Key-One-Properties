@forelse ($bills as $bill)
    @php
        $today = now(config('app.display_timezone'))->startOfDay();
        $open = in_array($bill->status, ['approved', 'paid'], true);
        $outstanding = $open ? App\Support\Money::sub((string) $bill->total_amount, (string) ($bill->allocated ?? '0')) : null;
        $overdue = $open && $bill->due_date && $bill->due_date->lt($today) && App\Support\Money::isPositive($outstanding);
    @endphp
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('supplier-bills.show', $bill) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $bill->bill_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($bill->invoice_date) }}@if ($bill->supplier_invoice_no) · {{ $bill->supplier_invoice_no }}@endif</p></td>
        @if ($showSupplier)<td class="text-ink-700"><x-ref :to="$bill->supplier">{{ $bill->supplier->name }}</x-ref></td>@endif
        <td class="whitespace-nowrap {{ $overdue ? 'font-medium text-danger-600' : 'text-ink-700' }}">{{ App\Support\Format::date($bill->due_date) }}</td>
        <td class="text-right"><x-money :value="$bill->total_amount" /></td>
        <td class="text-right">@if ($outstanding !== null)<x-money :value="$outstanding" @class(['font-semibold text-ink-900' => App\Support\Money::isPositive($outstanding)]) />@else<span class="text-ink-400">—</span>@endif</td>
        <td><x-status-badge :status="$bill->status" /></td>
    </tr>
@empty
    <tr><td colspan="{{ $showSupplier ? 6 : 5 }}"><x-empty-state icon="file" title="No bills" /></td></tr>
@endforelse
