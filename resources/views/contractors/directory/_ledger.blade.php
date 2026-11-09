@forelse ($rows as $row)
    <tr>
        <td class="whitespace-nowrap">{{ App\Support\Format::date($row->entry_date) }}</td>
        <td><x-badge :tone="match ($row->entry_type) { 'bill' => 'warning', 'payment' => 'success', 'advance' => 'info', 'retention_release' => 'accent', 'reversal' => 'danger', default => 'neutral' }">{{ App\Models\ContractorLedgerEntry::TYPES[$row->entry_type] ?? $row->entry_type }}</x-badge></td>
        <td class="text-ink-700">{{ $row->description }}</td>
        <td class="text-right">@if ((float) $row->charge_amount)<x-money :value="$row->charge_amount" />@endif</td>
        <td class="text-right">@if ((float) $row->payment_amount)<x-money :value="$row->payment_amount" />@endif</td>
        <td class="text-right font-medium"><x-money :value="$row->running_balance" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="file" title="No ledger entries" /></td></tr>
@endforelse
