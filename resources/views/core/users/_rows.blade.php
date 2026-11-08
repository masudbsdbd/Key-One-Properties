@forelse ($users as $user)
    <tr>
        <td>
            <div class="flex items-center gap-3">
                <x-avatar :name="$user->name" />
                <div class="min-w-0">
                    <p class="truncate font-medium text-ink-900">{{ $user->name }}</p>
                    <p class="truncate text-[13px] text-ink-500">{{ $user->email ?: $user->designation }}</p>
                </div>
            </div>
        </td>
        <td class="whitespace-nowrap">{{ $user->mobile ?: '—' }}</td>
        <td>@foreach ($user->roles as $role)<x-badge tone="accent">{{ $role->name }}</x-badge>@endforeach</td>
        <td class="text-ink-600">{{ $user->departments->pluck('name')->join(', ') ?: '—' }}</td>
        <td>
            <x-badge :tone="['active' => 'success', 'inactive' => 'neutral', 'locked' => 'danger'][$user->status] ?? 'neutral'">{{ \App\Models\User::STATUSES[$user->status] ?? $user->status }}</x-badge>
            @if ($user->hasTwoFactorEnabled())<x-badge tone="info" class="ml-1">2FA</x-badge>@endif
        </td>
        <td class="whitespace-nowrap text-ink-600">{{ $user->last_login_at?->timezone(config('app.display_timezone'))->format('d M Y, h:i A') ?? '—' }}</td>
        <td class="text-right whitespace-nowrap">
            @can('update', $user)
                <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('users.edit', $user) }}" aria-label="Edit {{ $user->name }}"><x-icon name="edit" size="h-4 w-4" /></button>
            @endcan
            @can('delete', $user)
                <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-row-action="delete" data-url="{{ route('users.destroy', $user) }}" data-confirm="Delete {{ $user->name }}?" data-confirm-text="Delete" aria-label="Delete {{ $user->name }}"><x-icon name="trash" size="h-4 w-4" /></button>
            @endcan
        </td>
    </tr>
@empty
    <tr><td colspan="7"><x-empty-state icon="users" title="No users found" /></td></tr>
@endforelse
