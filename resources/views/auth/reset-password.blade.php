<x-layouts.guest title="Set new password">
    <h1 class="text-[28px] font-semibold tracking-tight text-ink-900">Set new password</h1>

    <form method="POST" action="{{ route('password.update') }}" data-ajax-form class="mt-6 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form.input name="email" type="email" label="Email" autocomplete="email" icon="mail" required autofocus />
        <x-form.input name="password" type="password" label="New password" autocomplete="new-password" icon="lock" hint="At least 8 characters with letters and numbers" required />
        <x-form.input name="password_confirmation" type="password" label="Confirm password" autocomplete="new-password" icon="lock" required />
        <x-button type="submit" class="h-11 w-full">Update password</x-button>
    </form>
</x-layouts.guest>
