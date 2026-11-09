@php $showInvestor ??= true; @endphp
@forelse ($transactions as $t)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('investor-capital.show', $t) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $t->transaction_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($t->transaction_date) }}</p></td>
        @if ($showInvestor)<td class="text-ink-700">{{ $t->investor->name }}<span class="block text-[13px] text-ink-500">{{ $t->investor->investor_code }}</span></td>@endif
        <td class="text-ink-700">{{ $t->project->code }}<span class="block font-medium text-ink-900">Unit {{ $t->flat->flat_no }}</span></td>
        <td><x-badge :tone="$t->transaction_type === 'contribution' ? 'success' : 'warning'">{{ App\Models\InvestorCapitalTransaction::TYPES[$t->transaction_type] }}</x-badge></td>
        <td class="text-right font-medium"><x-money :value="$t->amount" /></td>
        <td><x-status-badge :status="$t->status" :labels="App\Models\InvestorCapitalTransaction::STATUSES" /></td>
    </tr>
@empty
    <tr><td colspan="{{ $showInvestor ? 6 : 5 }}"><x-empty-state icon="tag" title="No capital transactions" /></td></tr>
@endforelse
