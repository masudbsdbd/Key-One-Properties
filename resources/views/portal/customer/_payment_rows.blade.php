@forelse ($payments as $p)
    <tr>
        <td class="whitespace-nowrap"><span class="font-medium text-ink-900">{{ $p->payment_no }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($p->payment_date) }}</span></td>
        <td class="text-ink-700">Unit {{ ($p->sale ?? $p->booking)->flat->flat_no }}<span class="block text-[13px] text-ink-500">{{ App\Models\CustomerPayment::METHODS[$p->method] ?? $p->method }}{{ $p->reference_no ? ' · '.$p->reference_no : '' }}</span></td>
        <td class="text-[13px] text-ink-600">
            @foreach ($p->allocations->whereNull('reversal_of_id')->filter(fn ($a) => App\Support\Money::isPositive($a->amount)) as $a)
                <span class="block">{{ match ($a->allocation_type) { 'booking' => 'Booking money', 'unapplied_advance' => 'Advance', 'late_fee' => 'Late fee #'.$a->item?->sequence_no, default => 'Installment #'.$a->item?->sequence_no } }} · {{ App\Support\Format::money($a->amount) }}</span>
            @endforeach
        </td>
        <td class="text-right font-medium"><x-money :value="$p->amount" /></td>
        <td>@if ($p->status === 'reversed')<x-badge tone="danger">Reversed</x-badge>@else<x-badge tone="success">Received</x-badge>@endif</td>
        <td class="text-right">@if ($canReceipts && $p->receipt)<x-document-menu :url="route('customer.receipts.show', $p->public_id)" compact />@endif</td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="tag" title="No payments" /></td></tr>
@endforelse
