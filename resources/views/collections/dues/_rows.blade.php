@php($today = now(config('app.display_timezone'))->startOfDay())
@forelse ($items as $i)
    @php($late = (int) $i->due_date->diffInDays($today, false))
    <tr>
        <td><a href="{{ route('customers.statement', $i->plan->customer) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $i->plan->customer->full_name }}</a><span class="block text-[13px] text-ink-500">{{ App\Support\Phone::display($i->plan->customer->primary_mobile) }}</span></td>
        <td><a href="{{ route('installment-plans.show', $i->plan) }}" class="text-accent-700 hover:underline">{{ $i->plan->sale->sale_no }}</a><span class="block text-[13px] text-ink-500">{{ $i->plan->sale->project->code }} · Unit {{ $i->plan->sale->flat->flat_no }} · #{{ $i->sequence_no }}</span></td>
        <td class="whitespace-nowrap {{ $late > 0 ? 'font-medium text-danger-600' : 'text-ink-700' }}">{{ App\Support\Format::date($i->due_date) }}@if ($late > 0)<span class="block text-[13px]">{{ $late }} days late</span>@endif</td>
        <td class="text-right"><x-money :value="$i->principalOutstanding()" /></td>
        <td class="text-right">@if (App\Support\Money::isPositive($i->feeOutstanding()))<x-money :value="$i->feeOutstanding()" class="text-warning-600" />@else<span class="text-ink-400">—</span>@endif</td>
        <td class="text-right font-semibold"><x-money :value="$i->outstanding()" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="check" title="Nothing due" /></td></tr>
@endforelse
