@php($showValue = auth()->user()->can('materials.valuation.view'))
@forelse ($warehouses as $warehouse)
    <tr>
        <td class="font-medium text-ink-900">{{ $warehouse->code }}</td>
        <td>{{ $warehouse->name }}@if ($warehouse->address)<span class="block text-[13px] text-ink-500">{{ $warehouse->address }}</span>@endif</td>
        <td>@if ($warehouse->project)<x-ref :to="$warehouse->project" class="text-ink-700">{{ $warehouse->project->name }}</x-ref>@else<x-badge tone="info">Company</x-badge>@endif</td>
        <td class="text-right tabular-nums">{{ $warehouse->items_count }}</td>
        <td class="text-right">@if ($showValue)<x-money :value="$warehouse->stock_value ?? 0" :decimals="false" />@else<span class="text-ink-400">—</span>@endif</td>
        <td><x-status-badge :status="$warehouse->is_active ? 'active' : 'inactive'" /></td>
        <td class="text-right">
            @can('materials.manage')
                <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('warehouses.edit', $warehouse) }}" aria-label="Edit {{ $warehouse->name }}"><x-icon name="edit" size="h-4 w-4" /></button>
            @endcan
        </td>
    </tr>
@empty
    <tr><td colspan="7"><x-empty-state icon="building" title="No warehouses" /></td></tr>
@endforelse
