@php($canManage = auth()->user()->can('manageMembers', $project))
@if ($members->isEmpty())
    <x-empty-state icon="users" title="No team members" />
@else
    <ul class="divide-y divide-line">
        @foreach ($members as $member)
            <li class="flex items-center gap-4 px-5 py-3.5">
                <x-avatar :name="$member->name" />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium text-ink-900">{{ $member->name }}</p>
                    <p class="truncate text-[13px] text-ink-500">{{ $member->pivot->membership_role ?: ($member->designation ?: $member->roleLabel()) }}</p>
                </div>
                @if ($member->pivot->is_primary)<x-badge tone="warning">Primary</x-badge>@endif
                @if ($canManage)
                    @unless ($member->pivot->is_primary)
                        <button type="button" class="btn btn-ghost btn-sm" data-action="post" data-url="{{ route('projects.members.primary', [$project, $member]) }}">Make primary</button>
                    @endunless
                    <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-action="delete" data-url="{{ route('projects.members.destroy', [$project, $member]) }}" data-confirm="Remove {{ $member->name }} from this project?" data-confirm-text="Remove" aria-label="Remove {{ $member->name }}"><x-icon name="x" size="h-4 w-4" /></button>
                @endif
            </li>
        @endforeach
    </ul>
@endif
