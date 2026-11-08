@php($actor = auth()->user())
@forelse ($roles as $role)
    @php($editable = $actor->can('roles.manage') && ! $role->isSuperAdmin() && ($actor->isSuperAdmin() || ! $actor->hasRole($role)))
    <tr>
        <td>
            <p class="font-medium text-ink-900">{{ $role->name }}</p>
            @if ($role->description)<p class="text-[13px] text-ink-500">{{ $role->description }}</p>@endif
        </td>
        <td>
            @if ($role->isSuperAdmin())
                <x-badge tone="warning">All permissions</x-badge>
            @else
                <x-badge tone="accent">{{ $role->permissions_count }}</x-badge>
            @endif
        </td>
        <td class="tabular-nums">{{ $role->users_count }}</td>
        <td class="text-right whitespace-nowrap">
            @if ($editable)
                <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('roles.edit', $role) }}" data-size="max-w-4xl" aria-label="Edit {{ $role->name }}"><x-icon name="edit" size="h-4 w-4" /></button>
                <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-row-action="delete" data-url="{{ route('roles.destroy', $role) }}" data-confirm="Delete role {{ $role->name }}?" data-confirm-text="Delete" aria-label="Delete {{ $role->name }}"><x-icon name="trash" size="h-4 w-4" /></button>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="4"><x-empty-state icon="shield" title="No roles found" /></td></tr>
@endforelse
