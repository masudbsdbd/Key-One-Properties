@forelse ($departments as $department)
    <tr>
        <td>
            <div class="flex items-center gap-3">
                <span class="icon-tile icon-tile-sm tone-accent"><x-icon name="briefcase" size="h-4 w-4" /></span>
                <div>
                    <p class="font-medium text-ink-900">{{ $department->name }}</p>
                    @if ($department->description)<p class="text-[13px] text-ink-500">{{ $department->description }}</p>@endif
                </div>
            </div>
        </td>
        <td class="tabular-nums">{{ $department->users_count }}</td>
        <td><x-badge :tone="$department->status === 'active' ? 'success' : 'neutral'">{{ App\Models\Department::STATUSES[$department->status] ?? $department->status }}</x-badge></td>
        <td class="text-right whitespace-nowrap">
            @can('departments.manage')
                <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('departments.edit', $department) }}" aria-label="Edit {{ $department->name }}"><x-icon name="edit" size="h-4 w-4" /></button>
                <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-row-action="delete" data-url="{{ route('departments.destroy', $department) }}" data-confirm="Delete {{ $department->name }}?" data-confirm-text="Delete" aria-label="Delete {{ $department->name }}"><x-icon name="trash" size="h-4 w-4" /></button>
            @endcan
        </td>
    </tr>
@empty
    <tr><td colspan="4"><x-empty-state icon="briefcase" title="No departments found" /></td></tr>
@endforelse
