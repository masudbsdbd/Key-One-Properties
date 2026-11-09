@forelse ($payments as $p)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('customer-payments.show', $p) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $p->payment_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($p->payment_date) }}</p></td>
        <td class="text-ink-700"><x-ref :to="$p->customer">{{ $p->customer->full_name }}</x-ref></td>
        <td class="text-ink-700">@if ($p->sale)<x-ref :to="$p->sale">{{ $p->sale->sale_no }}</x-ref>@else<x-ref :to="$p->booking">{{ $p->booking?->booking_no }}</x-ref>@endif<span class="block text-[13px] text-ink-500">{{ App\Models\CustomerPayment::PURPOSES[$p->purpose] }} · <x-ref :to="$p->project">{{ $p->project->code }}</x-ref></span></td>
        <td class="text-ink-700">{{ App\Models\CustomerPayment::METHODS[$p->method] ?? $p->method }}@if ($p->reference_no)<span class="block text-[13px] text-ink-500">{{ $p->reference_no }}</span>@endif</td>
        <td class="text-right font-medium"><x-money :value="$p->amount" /></td>
        <td><x-status-badge :status="$p->status" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="tag" title="No payments" /></td></tr>
@endforelse
