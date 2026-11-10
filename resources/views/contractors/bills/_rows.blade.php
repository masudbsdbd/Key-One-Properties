@forelse ($bills as $bill)
    @php
        $outstanding = in_array($bill->status, ['approved', 'paid'], true) ? App\Support\Money::sub((string) $bill->net_payable, (string) ($bill->paid ?? '0')) : null;
        $payable = $bill->status === 'approved' && App\Support\Money::isPositive(App\Support\Money::sub((string) $bill->net_payable, (string) ($bill->reserved ?? '0')));
    @endphp
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('contractor-bills.show', $bill) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $bill->bill_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($bill->bill_date) }}@if ($bill->kind === 'retention_release') · Retention release @endif</p></td>
        @if ($showContractor)<td class="text-ink-700"><x-ref :to="$bill->contractor">{{ $bill->contractor->name }}</x-ref></td>@endif
        @if ($showOrder)<td class="text-ink-700"><a href="{{ route('work-orders.show', $bill->workOrder) }}" class="hover:text-accent-700">{{ $bill->workOrder->wo_no }}</a><span class="block max-w-48 truncate text-[13px] text-ink-400">{{ $bill->workOrder->title }}</span></td>@endif
        <td class="text-right"><x-money :value="$bill->gross_amount" /></td>
        <td class="text-right font-medium"><x-money :value="$bill->net_payable" /></td>
        <td class="text-right">@if ($outstanding !== null)<x-money :value="$outstanding" @class(['font-semibold text-ink-900' => App\Support\Money::isPositive($outstanding)]) />@else<span class="text-ink-400">—</span>@endif</td>
        <td><x-status-badge :status="$bill->status" /></td>
        <td class="text-right">
            @if ($payable && Gate::allows('wo:pay', $bill->workOrder))
                <x-button size="sm" variant="secondary" icon="tag" data-remote-modal="{{ route('contractor-payments.create', ['work_order' => $bill->workOrder->public_id, 'bill' => $bill->public_id]) }}" data-size="max-w-2xl">Pay</x-button>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="{{ 6 + (int) $showContractor + (int) $showOrder }}"><x-empty-state icon="file" title="No bills" /></td></tr>
@endforelse
