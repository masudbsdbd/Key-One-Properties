@php $user = auth()->user(); @endphp
<x-card title="Portal Access" :padded="false">
    <x-slot:actions>@can('portal.links.manage')<x-button size="sm" icon="user-plus" data-remote-modal="{{ route('portal-access.create', [$type, $owner->public_id]) }}" data-size="max-w-lg">New Account</x-button>@endcan</x-slot:actions>
    @forelse ($links as $link)
        @php
            $state = $link->revoked_at ? ['Revoked', 'danger'] : ($link->verified_at ? ['Active', 'success'] : ['Pending Verification', 'warning']);
            $userGrants = $grants->get($link->user_id, collect());
        @endphp
        <div class="border-b border-line px-5 py-4 last:border-0">
            <div class="flex flex-wrap items-center gap-3">
                <x-avatar :name="$link->user->name" size="h-10 w-10 text-sm" />
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-ink-900">{{ $link->user->name }}</p>
                    <p class="text-[13px] text-ink-500">{{ $link->user->email ?: App\Support\Phone::display($link->user->mobile) }}@if ($link->user->last_login_at) · last sign-in {{ App\Support\Format::date($link->user->last_login_at) }}@endif</p>
                </div>
                <x-badge :tone="$state[1]">{{ $state[0] }}</x-badge>
                @if (! $link->revoked_at)
                    @can('portal.links.manage')
                        @unless ($link->verified_at)
                            <x-button size="sm" icon="check" data-action="post" data-url="{{ route('portal-access.links.verify', [$type, $link->public_id]) }}" data-confirm="Verify portal access for {{ $link->user->name }}?" data-confirm-text="Verify" data-tone="primary" :disabled="$link->created_by === $user->id && ! $user->isSuperAdmin()" data-disabled-reason="Another administrator must verify">Verify</x-button>
                        @endunless
                        <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('portal-access.links.password.form', [$type, $link->public_id]) }}" data-size="max-w-md" aria-label="Reset password of {{ $link->user->name }}"><x-icon name="key" size="h-4 w-4" /></button>
                        <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-remote-modal="{{ route('portal-access.links.revoke.form', [$type, $link->public_id]) }}" data-size="max-w-md" aria-label="Revoke access of {{ $link->user->name }}"><x-icon name="lock" size="h-4 w-4" /></button>
                    @endcan
                @endif
            </div>
            @if ($type === 'investor' && ($userGrants->isNotEmpty() || (! $link->revoked_at && $user->can('portal.grants.manage'))))
                <div class="mt-3 rounded-card border border-line bg-surface-muted">
                    <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                        <p class="text-sm font-medium text-ink-900">Grants</p>
                        @if (! $link->revoked_at)
                            @can('portal.grants.manage')<x-button size="sm" variant="secondary" icon="plus" data-remote-modal="{{ route('portal-access.grants.create', $link->public_id) }}" data-size="max-w-lg" :disabled="! $link->verified_at" data-disabled-reason="Verify the account first">Add Grant</x-button>@endcan
                        @endif
                    </div>
                    @foreach ($userGrants as $g)
                        <div class="flex flex-wrap items-center gap-3 border-t border-line px-4 py-2.5 text-sm">
                            <span class="min-w-0 flex-1"><span class="font-medium text-ink-900">{{ App\Modules\Portal\Services\PortalAccess::GRANTABLE[$g->capability] ?? $g->capability }}</span><span class="block text-[13px] text-ink-500">{{ $service->resourceLabel($g->resource()) }} · {{ $g->granter->name }} · {{ App\Support\Format::date($g->created_at) }}@if ($g->expires_at) · until {{ App\Support\Format::date($g->expires_at) }}@endif</span></span>
                            @if ($g->isCurrent())
                                <x-badge tone="success">Active</x-badge>
                                @can('portal.grants.manage')<button type="button" class="btn btn-ghost btn-icon text-danger-600" data-action="delete" data-url="{{ route('portal-access.grants.revoke', $g) }}" data-confirm="Revoke this grant?" data-confirm-text="Revoke" aria-label="Revoke grant"><x-icon name="x" size="h-4 w-4" /></button>@endcan
                            @else
                                <x-badge>{{ $g->revoked_at ? 'Revoked' : 'Expired' }}</x-badge>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <x-empty-state icon="lock" title="No portal accounts" />
    @endforelse
</x-card>
