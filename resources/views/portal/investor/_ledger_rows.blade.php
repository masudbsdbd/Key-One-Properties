@php
    $words = ['capital_in' => 'You Invested', 'capital_out' => 'Capital Returned to You', 'profit_payable' => 'Profit Approved for You', 'profit_paid' => 'Profit Paid to You', 'reversal' => 'Entry Cancelled'];
    $signed = fn ($v) => ((float) $v > 0 ? '+' : '').App\Support\Format::money($v);
@endphp
@forelse ($rows as $r)
    <tr>
        <td class="whitespace-nowrap">{{ App\Support\Format::date($r->entry_date) }}</td>
        <td class="min-w-56"><p class="font-medium text-ink-900">{{ $words[$r->entry_kind] ?? App\Models\InvestorLedgerEntry::KINDS[$r->entry_kind] ?? $r->entry_kind }}</p><p class="text-[13px] text-ink-500">{{ $r->description }}</p></td>
        <td @class(['whitespace-nowrap text-right tabular-nums', 'text-success-600' => (float) $r->capital_effect > 0, 'text-danger-600' => (float) $r->capital_effect < 0])>@if ((float) $r->capital_effect){{ $signed($r->capital_effect) }}@endif</td>
        <td @class(['whitespace-nowrap text-right tabular-nums', 'text-success-600' => (float) $r->profit_effect > 0, 'text-ink-700' => (float) $r->profit_effect < 0])>@if ((float) $r->profit_effect){{ $signed($r->profit_effect) }}@endif</td>
        <td class="whitespace-nowrap text-right font-medium"><x-money :value="$r->capital_balance" /></td>
        <td class="whitespace-nowrap text-right font-medium"><x-money :value="$r->profit_balance" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="file" title="No entries" /></td></tr>
@endforelse
