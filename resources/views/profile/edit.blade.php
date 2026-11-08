<x-layouts.app title="Profile">
    <x-page-header title="Profile" :breadcrumbs="['Dashboard' => route('dashboard'), 'Profile' => null]" />

    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        <button type="button" role="tab" class="tab" id="tab-account" data-tab="account" aria-controls="panel-account">Account</button>
        <button type="button" role="tab" class="tab" id="tab-security" data-tab="security" aria-controls="panel-security">Security</button>
        <button type="button" role="tab" class="tab" id="tab-sessions" data-tab="sessions" aria-controls="panel-sessions">Sessions</button>
    </div>

    <div id="panel-account" role="tabpanel" aria-labelledby="tab-account" class="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <x-card title="Account">
            <form method="POST" action="{{ route('profile.update') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
                @csrf @method('PUT')
                <x-form.input name="name" label="Full Name" :value="$user->name" required wrapper-class="sm:col-span-2" />
                <x-form.input name="email" type="email" label="Email" :value="$user->email" icon="mail" />
                <x-form.input name="mobile" label="Mobile" :value="$user->mobile" icon="phone" />
                <x-form.actions class="sm:col-span-2 mt-0">
                    <x-button type="submit" icon="check">Save</x-button>
                </x-form.actions>
            </form>
        </x-card>
        <x-card>
            <div class="flex flex-col items-center text-center">
                <x-avatar :name="$user->name" size="h-20 w-20 text-2xl" />
                <p class="mt-3 text-lg font-semibold text-ink-900">{{ $user->name }}</p>
                <p class="text-sm text-ink-500">{{ $user->roleLabel() }}</p>
            </div>
            <dl class="mt-6 space-y-4 border-t border-line pt-5">
                <div class="flex justify-between gap-4"><dt class="text-sm text-ink-500">Last sign-in</dt><dd class="text-sm text-ink-900">{{ $user->last_login_at ? $user->last_login_at->timezone(config('app.display_timezone'))->format('d M Y, h:i A') : '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-sm text-ink-500">Two-factor</dt><dd>@if ($user->hasTwoFactorEnabled())<x-badge tone="success">Enabled</x-badge>@else<x-badge>Off</x-badge>@endif</dd></div>
            </dl>
        </x-card>
    </div>

    <div id="panel-security" role="tabpanel" aria-labelledby="tab-security" class="grid gap-6 lg:grid-cols-2" hidden>
        <x-card title="Change Password">
            <form method="POST" action="{{ route('profile.password') }}" data-ajax-form data-reset class="space-y-5">
                @csrf @method('PUT')
                <x-form.input name="current_password" type="password" label="Current password" autocomplete="current-password" required />
                <x-form.input name="password" type="password" label="New password" autocomplete="new-password" hint="At least 8 characters with letters and numbers" required />
                <x-form.input name="password_confirmation" type="password" label="Confirm password" autocomplete="new-password" required />
                <x-form.actions class="mt-0"><x-button type="submit">Update password</x-button></x-form.actions>
            </form>
        </x-card>

        <x-card title="Two-Factor Authentication" data-two-factor-card>
            <x-slot:actions>
                @if ($user->hasTwoFactorEnabled())<x-badge tone="success">Enabled</x-badge>@else<x-badge>Off</x-badge>@endif
            </x-slot:actions>

            @if ($user->hasTwoFactorEnabled())
                <div class="flex flex-wrap gap-3">
                    <x-button variant="secondary" icon="key" data-modal-open="modal-recovery">New recovery codes</x-button>
                    @unless ($user->two_factor_required)
                        <x-button variant="danger-soft" data-modal-open="modal-2fa-disable">Disable</x-button>
                    @endunless
                </div>
            @else
                <div data-2fa-start>
                    <form method="POST" action="{{ route('profile.two-factor.enable') }}" data-ajax-form data-two-factor-enable>
                        @csrf
                        <x-button type="submit" icon="smartphone">Set up authenticator</x-button>
                    </form>
                </div>
                <div data-2fa-setup hidden>
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                        <div class="rounded-card border border-line bg-white p-3" data-2fa-qr></div>
                        <div class="min-w-0 flex-1">
                            <p class="detail-label">Setup key</p>
                            <p class="mt-1 break-all font-mono text-sm text-ink-900" data-2fa-secret></p>
                            <form method="POST" action="{{ route('profile.two-factor.confirm') }}" data-ajax-form data-two-factor-confirm class="mt-5 space-y-4">
                                @csrf
                                <x-form.input name="code" id="two-factor-code" label="Authenticator code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
                                <x-button type="submit" icon="check">Confirm</x-button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            <div data-2fa-codes hidden class="mt-5 rounded-card border border-warning-600/30 bg-warning-50 p-4">
                <p class="text-sm font-semibold text-warning-600">Recovery codes</p>
                <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm text-ink-900" data-2fa-code-list></ul>
            </div>
        </x-card>
    </div>

    <div id="panel-sessions" role="tabpanel" aria-labelledby="tab-sessions" hidden>
        <x-card title="Active Sessions" :padded="false">
            <x-slot:actions>
                <x-button variant="secondary" size="sm" icon="logout" data-modal-open="modal-sessions">Sign out other devices</x-button>
            </x-slot:actions>
            <ul class="divide-y divide-line">
                @foreach ($sessions as $session)
                    <li class="flex items-center gap-4 px-5 py-4">
                        <span class="icon-tile icon-tile-md tone-info"><x-icon name="smartphone" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-ink-900">{{ $session->agent ?: 'Unknown device' }}</p>
                            <p class="text-[13px] text-ink-500">{{ $session->ip }} · {{ $session->last_active->diffForHumans() }}</p>
                        </div>
                        @if ($session->current)<x-badge tone="success">This device</x-badge>@endif
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>

    @push('modals')
        <x-modal id="modal-sessions" title="Sign out other devices" size="max-w-md">
            <form method="POST" action="{{ route('profile.sessions.logout-others') }}" data-ajax-form data-reset class="space-y-5">
                @csrf
                <x-form.input name="password" id="sessions-password" type="password" label="Password" autocomplete="current-password" required autofocus />
                <x-form.actions><x-button variant="secondary" data-modal-close>Cancel</x-button><x-button type="submit">Sign out</x-button></x-form.actions>
            </form>
        </x-modal>
        @if ($user->hasTwoFactorEnabled())
            <x-modal id="modal-recovery" title="New recovery codes" size="max-w-md">
                <form method="POST" action="{{ route('profile.two-factor.recovery') }}" data-ajax-form data-reset data-two-factor-recovery class="space-y-5">
                    @csrf
                    <x-form.input name="password" id="recovery-password" type="password" label="Password" autocomplete="current-password" required autofocus />
                    <x-form.actions><x-button variant="secondary" data-modal-close>Cancel</x-button><x-button type="submit">Generate</x-button></x-form.actions>
                </form>
            </x-modal>
            <x-modal id="modal-2fa-disable" title="Disable two-factor" size="max-w-md">
                <form method="POST" action="{{ route('profile.two-factor.disable') }}" data-ajax-form data-reload class="space-y-5">
                    @csrf @method('DELETE')
                    <x-form.input name="password" id="disable-password" type="password" label="Password" autocomplete="current-password" required autofocus />
                    <x-form.actions><x-button variant="secondary" data-modal-close>Cancel</x-button><x-button type="submit" variant="danger">Disable</x-button></x-form.actions>
                </form>
            </x-modal>
        @endif
    @endpush
</x-layouts.app>
