@php($canWaive = auth()->user()->can('collections.latefee.waive'))
@forelse ($assessments as $a)
    <tr>
        <td class="whitespace-nowrap">{{ App\Support\Format::date($a->assessed_on) }}<span class="block text-[13px] text-ink-500">{{ $a->period === 'once' ? 'One-time' : $a->period }}</span></td>
        <td class="text-ink-700">{{ $a->item->plan->customer->full_name }}</td>
        <td><a href="{{ route('installment-plans.show', $a->item->plan) }}" class="text-accent-700 hover:underline">{{ $a->item->plan->plan_no }}</a><span class="block text-[13px] text-ink-500">#{{ $a->item->sequence_no }} · Unit {{ $a->item->plan->sale->flat->flat_no }}</span></td>
        <td class="text-right font-medium"><x-money :value="$a->amount" /></td>
        <td><x-status-badge :status="$a->status" />@if ($a->reason)<span class="block text-[13px] text-ink-500">{{ $a->reason }}</span>@endif</td>
        <td class="text-right">@if ($a->status === 'posted' && $canWaive)<button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('late-fees.waive.form', $a) }}" data-size="max-w-md" aria-label="Waive late fee"><x-icon name="x" size="h-4 w-4" /></button>@endif</td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="check" title="No late fees charged" /></td></tr>
@endforelse
