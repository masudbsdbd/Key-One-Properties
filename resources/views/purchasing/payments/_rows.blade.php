@forelse ($payments as $payment)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('supplier-payments.show', $payment) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $payment->payment_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($payment->payment_date) }}</p></td>
        @if ($showSupplier)<td class="text-ink-700">{{ $payment->supplier->name }}</td>@endif
        <td class="text-ink-700">{{ App\Models\SupplierPayment::METHODS[$payment->payment_method] }}@if ($payment->payment_reference)<span class="block text-[13px] text-ink-500">{{ $payment->payment_reference }}</span>@endif</td>
        <td class="text-right font-medium"><x-money :value="$payment->amount" /></td>
        <td><x-status-badge :status="$payment->status" /></td>
    </tr>
@empty
    <tr><td colspan="{{ $showSupplier ? 5 : 4 }}"><x-empty-state icon="tag" title="No payments" /></td></tr>
@endforelse
