@forelse ($cheques as $cheque)
    <tr>
        <td class="whitespace-nowrap"><p class="font-medium text-ink-900">{{ $cheque->cheque_no }}</p>
            <p class="text-[13px] text-ink-500">{{ collect([$cheque->bank_name, $cheque->cheque_date ? 'Dated '.App\Support\Format::date($cheque->cheque_date) : null])->filter()->implode(' · ') ?: '—' }}</p></td>
        <td class="text-ink-700">@if ($cheque->customer)<x-ref :to="$cheque->customer" class="font-medium">{{ $cheque->customer->full_name }}</x-ref>@endif
            <p class="text-[13px] text-ink-500">@if ($cheque->source)<x-ref :to="$cheque->source">{{ $cheque->source->payment_no }}</x-ref> · @endif Received {{ App\Support\Format::date($cheque->received_on) }}</p></td>
        <td class="text-right font-medium"><x-money :value="$cheque->amount" /></td>
        <td class="whitespace-nowrap">@include('accounting.cheques._status')</td>
        <td class="text-right">@if ($canManage)@include('accounting.cheques._buttons')@endif</td>
    </tr>
@empty
    <tr><td colspan="5"><x-empty-state icon="note" title="No cheques" /></td></tr>
@endforelse
