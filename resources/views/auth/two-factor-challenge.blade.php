<x-layouts.guest title="Two-factor authentication">
    <h1 class="text-[28px] font-semibold tracking-tight text-ink-900">Verification code</h1>

    <form method="POST" action="{{ route('two-factor.verify') }}" data-ajax-form class="mt-6 space-y-5" data-two-factor-form>
        @csrf
        <div data-mode="code">
            <x-form.input name="code" label="Authenticator code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*" icon="smartphone" autofocus />
        </div>
        <div data-mode="recovery" hidden>
            <x-form.input name="recovery_code" label="Recovery code" autocomplete="off" icon="key" />
        </div>
        <x-button type="submit" class="h-11 w-full">Verify</x-button>
        <button type="button" class="w-full text-center text-sm font-medium text-accent-600 hover:text-accent-700" data-toggle-recovery>Use a recovery code</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-sm text-ink-500 hover:text-ink-900">Back to sign in</button>
    </form>
</x-layouts.guest>
