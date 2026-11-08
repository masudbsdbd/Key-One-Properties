@forelse ($rows as $r)
    <tr>
        <td class="whitespace-nowrap">{{ App\Support\Format::date($r->effective_at) }}</td>
        <td>{{ App\Models\ProjectCostAllocation::CATEGORIES[$r->cost_category] ?? $r->cost_category }}</td>
        <td class="text-ink-700">{{ $r->sourceLabel() }}@if ($r->reversal_of_id) <x-badge tone="danger">Reversal</x-badge>@endif</td>
        <td>{{ $r->flat ? 'Unit '.$r->flat->flat_no : 'Shared' }}</td>
        <td class="text-right font-medium"><x-money :value="$r->amount" /></td>
    </tr>
@empty
    <tr><td colspan="5"><x-empty-state icon="layers" title="No cost rows" /></td></tr>
@endforelse
