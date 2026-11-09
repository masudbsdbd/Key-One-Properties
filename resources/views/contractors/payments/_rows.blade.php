@forelse ($payments as $payment)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('contractor-payments.show', $payment) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $payment->payment_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($payment->payment_date) }}</p></td>
        @if ($showContractor)<td class="text-ink-700">{{ $payment->contractor->name }}</td>@endif
        <td class="whitespace-nowrap text-ink-700">@if ($payment->bill){{ $payment->bill->bill_no }}@else<x-badge tone="info">Advance</x-badge>@endif<span class="block text-[13px] text-ink-400">{{ $payment->workOrder->wo_no }}</span></td>
        <td class="text-ink-700">{{ App\Models\ContractorPayment::METHODS[$payment->payment_method] }}@if ($payment->reference)<span class="block text-[13px] text-ink-500">{{ $payment->reference }}</span>@endif</td>
        <td class="text-right font-medium"><x-money :value="$payment->amount" /></td>
        <td><x-status-badge :status="$payment->status" /></td>
    </tr>
@empty
    <tr><td colspan="{{ $showContractor ? 6 : 5 }}"><x-empty-state icon="tag" title="No payments" /></td></tr>
@endforelse
