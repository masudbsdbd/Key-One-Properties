<x-layouts.guest title="Sign in">
    <h1 class="text-[28px] font-semibold tracking-tight text-ink-900">Sign in</h1>

    <div class="mt-6 rounded-card border border-danger-500/30 bg-danger-50 p-4 text-sm text-danger-600" role="alert" data-lockout @if (! $lockedFor) hidden @endif>
        <div class="flex items-center gap-3">
            <x-icon name="lock" size="h-5 w-5 shrink-0" />
            <p>Too many failed attempts. Try again in <strong class="tabular-nums" data-lockout-timer>{{ gmdate('i:s', $lockedFor) }}</strong></p>
        </div>
    </div>

    <form method="POST" action="{{ route('login.store') }}" data-ajax-form data-silent="true" data-lockout-form data-locked-for="{{ $lockedFor }}" class="mt-6 space-y-5">
        @csrf
        <x-form.input name="login" label="Email or mobile" autocomplete="username" icon="user" required autofocus />
        <div>
            <x-form.input name="password" type="password" label="Password" autocomplete="current-password" icon="lock" required />
            <div class="mt-2 text-right">
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-accent-600 hover:text-accent-700">Forgot password?</a>
            </div>
        </div>
        <x-form.checkbox name="remember" label="Remember me" />
        <x-button type="submit" class="h-11 w-full">Sign in</x-button>
    </form>
</x-layouts.guest>
