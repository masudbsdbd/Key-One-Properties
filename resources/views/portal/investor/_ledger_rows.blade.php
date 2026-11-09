@forelse ($rows as $r)
    <tr>
        <td class="whitespace-nowrap">{{ App\Support\Format::date($r->entry_date) }}</td>
        <td><x-badge :tone="match ($r->entry_kind) { 'capital_in', 'profit_payable' => 'success', 'capital_out', 'profit_paid' => 'warning', default => 'danger' }">{{ App\Models\InvestorLedgerEntry::KINDS[$r->entry_kind] ?? $r->entry_kind }}</x-badge></td>
        <td class="text-ink-700">{{ $r->description }}</td>
        <td class="text-right">@if ((float) $r->capital_effect)<x-money :value="$r->capital_effect" />@endif</td>
        <td class="text-right">@if ((float) $r->profit_effect)<x-money :value="$r->profit_effect" />@endif</td>
        <td class="text-right font-medium"><x-money :value="$r->capital_balance" /></td>
        <td class="text-right font-medium"><x-money :value="$r->profit_balance" /></td>
    </tr>
@empty
    <tr><td colspan="7"><x-empty-state icon="file" title="No entries" /></td></tr>
@endforelse
