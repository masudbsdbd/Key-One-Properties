<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($user->exists) @method('PUT') @endif

    <x-form.input name="name" label="Full Name" :value="$user->name" required autofocus wrapper-class="sm:col-span-2" />
    <x-form.input name="email" type="email" label="Email" :value="$user->email" icon="mail" />
    <x-form.input name="mobile" label="Mobile" :value="$user->mobile" icon="phone" placeholder="01XXXXXXXXX" />
    <x-form.input name="designation" label="Designation" :value="$user->designation" />
    <x-form.select name="role" label="Role" :options="$roles" :selected="$user->roles->first()?->public_id" required />
    <x-form.select name="departments" label="Departments" :options="$departments" :selected="$user->departments->pluck('public_id')->all()" multiple placeholder="Select departments" wrapper-class="sm:col-span-2" />
    <x-form.select name="status" label="Status" :options="$statuses" :selected="$user->status" required />
    <div></div>
    <x-form.input name="password" type="password" label="{{ $user->exists ? 'New password' : 'Password' }}" autocomplete="new-password" :required="! $user->exists" hint="At least 8 characters with letters and numbers" />
    <x-form.input name="password_confirmation" type="password" label="Confirm password" autocomplete="new-password" :required="! $user->exists" />

    @can('users.manage_2fa')
        <div class="sm:col-span-2 flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-surface-muted px-4 py-3">
            <x-form.checkbox name="two_factor_required" label="Require two-factor authentication" :checked="$user->two_factor_required" />
            @if ($user->exists && $user->hasTwoFactorEnabled())
                <button type="button" class="btn btn-sm btn-danger-soft" data-action="post" data-url="{{ route('users.two-factor.reset', $user) }}" data-confirm="Reset two-factor for {{ $user->name }}?">Reset 2FA</button>
            @endif
        </div>
    @endcan

    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $user->exists ? 'Save' : 'Create User' }}</x-button>
    </x-form.actions>
</form>
