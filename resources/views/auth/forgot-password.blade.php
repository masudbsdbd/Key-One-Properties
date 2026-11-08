<x-layouts.guest title="Forgot password">
    <h1 class="text-[28px] font-semibold tracking-tight text-ink-900">Reset password</h1>

    <form method="POST" action="{{ route('password.email') }}" data-ajax-form data-reset class="mt-6 space-y-5">
        @csrf
        <x-form.input name="email" type="email" label="Email" autocomplete="email" icon="mail" required autofocus />
        <x-button type="submit" class="h-11 w-full">Send reset link</x-button>
    </form>

    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="font-medium text-accent-600 hover:text-accent-700">Back to sign in</a></p>
</x-layouts.guest>
