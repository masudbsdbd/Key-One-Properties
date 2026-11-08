@forelse ($sales as $s)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('sales.show', $s) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $s->sale_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($s->agreement_date) }}</p></td>
        <td class="text-ink-700">{{ $s->project->code }}<span class="block font-medium text-ink-900">Unit {{ $s->flat->flat_no }}</span></td>
        <td class="text-ink-700">{{ $s->customer->full_name }}<span class="block text-[13px] text-ink-500">{{ $s->customer->customer_code }}</span></td>
        <td class="text-right font-medium"><x-money :value="$s->net_contract_value" /></td>
        <td><x-status-badge :status="$s->status" :labels="App\Models\FlatSale::STATUSES" /></td>
    </tr>
@empty
    <tr><td colspan="5"><x-empty-state icon="door" title="No sales" /></td></tr>
@endforelse
