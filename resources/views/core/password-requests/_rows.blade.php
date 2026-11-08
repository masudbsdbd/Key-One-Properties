@php($actor = auth()->user())
@forelse ($requests as $item)
    <tr>
        <td>
            <div class="flex items-center gap-3">
                <x-avatar :name="$item->user->name" />
                <div class="min-w-0">
                    <p class="truncate font-medium text-ink-900">{{ $item->user->name }}</p>
                    <p class="truncate text-[13px] text-ink-500">{{ $item->user->email ?: $item->user->designation }}</p>
                </div>
            </div>
        </td>
        <td class="whitespace-nowrap">{{ App\Support\Phone::display($item->user->mobile) }}</td>
        <td class="whitespace-nowrap text-ink-600">{{ App\Support\Format::datetime($item->updated_at) }}</td>
        <td class="text-ink-600">{{ $item->requested_ip ?: '—' }}</td>
        <td>
            <x-badge :tone="App\Models\PasswordResetRequest::STATUS_TONES[$item->status] ?? 'neutral'">{{ App\Models\PasswordResetRequest::STATUSES[$item->status] }}</x-badge>
            @if ($item->handler)<span class="block text-[13px] text-ink-500">{{ $item->handler->name }}</span>@endif
        </td>
        <td class="whitespace-nowrap text-right">
            @if ($item->status === 'pending')
                @can('resetPassword', $item->user)
                    <x-button size="sm" icon="key" data-remote-modal="{{ route('users.password.edit', $item->user) }}" data-size="max-w-md">Reset</x-button>
                @endcan
                <x-button size="sm" variant="ghost" data-row-action="post" data-url="{{ route('password-requests.dismiss', $item) }}" data-confirm="Dismiss this request?" data-confirm-text="Dismiss">Dismiss</x-button>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="key" title="No requests" /></td></tr>
@endforelse
