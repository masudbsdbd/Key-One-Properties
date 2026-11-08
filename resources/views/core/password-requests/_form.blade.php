<form method="POST" action="{{ route('users.password.update', $user) }}" data-ajax-form class="space-y-5">
    @csrf
    <x-form.input name="password" id="reset-password" type="password" label="New password" autocomplete="new-password" hint="At least 8 characters with letters and numbers" required autofocus />
    <x-form.input name="password_confirmation" id="reset-password-confirmation" type="password" label="Confirm password" autocomplete="new-password" required />
    <x-form.checkbox name="must_change_password" label="Ask the user to change it at next sign-in" :checked="true" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Reset Password</x-button>
    </x-form.actions>
</form>
