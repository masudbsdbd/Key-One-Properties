<x-layouts.guest title="Forgot password">
    <h1 class="text-[28px] font-semibold tracking-tight text-ink-900">Forgot password</h1>

    <div class="mt-6 rounded-card border border-success-600/30 bg-success-50 p-4 text-sm text-success-600" role="status" data-request-sent hidden>
        <div class="flex items-center gap-3"><x-icon name="check" size="h-5 w-5 shrink-0" /><p>{{ App\Http\Controllers\Auth\PasswordResetController::MESSAGE }}</p></div>
    </div>

    <form method="POST" action="{{ route('password.request.store') }}" data-ajax-form data-reset data-silent="true" data-no-toast data-password-request class="mt-6 space-y-5">
        @csrf
        <x-form.input name="login" label="Email or mobile" autocomplete="username" icon="user" required autofocus />
        <x-form.actions class="mt-0 justify-stretch"><x-button type="submit" class="h-11 w-full">Send request</x-button></x-form.actions>
    </form>

    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="font-medium text-accent-600 hover:text-accent-700">Back to sign in</a></p>
</x-layouts.guest>
