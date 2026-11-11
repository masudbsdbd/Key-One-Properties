<form method="POST" action="{{ route('portal-access.links.password', [$type, $link->public_id]) }}" data-ajax-form class="space-y-5">
    @csrf
    @method('PUT')
    <x-form.input name="password" type="password" label="New Password" required autofocus autocomplete="new-password" hint="At least 8 characters, letters and numbers" />
    <x-form.input name="password_confirmation" type="password" label="Confirm Password" required autocomplete="new-password" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Reset Password</x-button>
    </x-form.actions>
</form>
