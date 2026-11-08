@forelse ($rows as $row)
    <tr>
        <td class="font-medium text-ink-900">{{ $row['name'] }}</td>
        <td><span class="inline-flex items-center gap-2"><x-icon name="phone" size="h-4 w-4 text-ink-400" />{{ $row['phone'] }}</span></td>
        <td>
            <x-badge :tone="['New' => 'violet', 'Contacted' => 'info', 'Qualified' => 'success', 'Won' => 'warning', 'Lost' => 'danger'][$row['stage']]">{{ $row['stage'] }}</x-badge>
        </td>
        <td class="text-right">
            <button type="button" class="btn btn-ghost btn-icon" data-row-action="delete" data-url="#" data-confirm="Delete this record?" aria-label="Delete"><x-icon name="trash" size="h-4 w-4" /></button>
        </td>
    </tr>
@empty
    <tr><td colspan="4"><x-empty-state icon="search" title="No results" /></td></tr>
@endforelse
