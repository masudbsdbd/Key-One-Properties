@forelse ($rows as $r)
    <tr>
        <td class="whitespace-nowrap">{{ App\Support\Format::date($r->entry_date) }}</td>
        <td><x-badge :tone="match ($r->entry_type) { 'sale', 'late_fee', 'refund' => 'warning', 'payment', 'fee_waiver', 'sale_cancelled' => 'success', 'reversal' => 'danger', default => 'neutral' }">{{ App\Models\CustomerStatementEntry::TYPES[$r->entry_type] ?? $r->entry_type }}</x-badge></td>
        <td class="text-ink-700">{{ $r->description }}</td>
        <td class="text-right">@if ((float) $r->charge_amount)<x-money :value="$r->charge_amount" />@endif</td>
        <td class="text-right">@if ((float) $r->credit_amount)<x-money :value="$r->credit_amount" />@endif</td>
        <td class="text-right font-medium"><x-money :value="$r->running_balance" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="file" title="No entries" /></td></tr>
@endforelse
