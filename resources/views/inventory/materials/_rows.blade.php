@forelse ($materials as $material)
    <tr>
        <td class="whitespace-nowrap font-medium text-ink-900">{{ $material->sku }}</td>
        <td>{{ $material->name }}</td>
        <td class="text-ink-600">{{ $material->category->name }}</td>
        <td class="text-ink-600">{{ $material->unit->code }}</td>
        <td class="text-right tabular-nums">{{ rtrim(rtrim($material->reorder_level, '0'), '.') ?: '0' }}</td>
        <td class="text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $material->owned_total, 3, '.', ','), '0'), '.') ?: '0' }}</td>
        <td><x-status-badge :status="$material->is_active ? 'active' : 'inactive'" /></td>
        <td class="whitespace-nowrap text-right">
            @can('materials.manage')
                <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('materials.edit', $material) }}" aria-label="Edit {{ $material->name }}"><x-icon name="edit" size="h-4 w-4" /></button>
                <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-row-action="delete" data-url="{{ route('materials.destroy', $material) }}" data-confirm="Delete {{ $material->name }}?" data-confirm-text="Delete" aria-label="Delete {{ $material->name }}"><x-icon name="trash" size="h-4 w-4" /></button>
            @endcan
        </td>
    </tr>
@empty
    <tr><td colspan="8"><x-empty-state icon="tag" title="No materials found" /></td></tr>
@endforelse
