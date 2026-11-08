<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\LoginThrottle;
use App\Services\Auth\SecurityLog;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const TIMING_GUARD_HASH = '$2y$12$kceFpAfKvwZu0MgS48/mYumwC8iZjFLp6vQGXRJIAwfxRl30VmhSm';

    public function __construct(private LoginThrottle $throttle) {}

    public function show(Request $request): View
    {
        return view('auth.login', [
            'lockedFor' => $this->throttle->lockedFor($request),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $identifier = $credentials['login'];

        if ($seconds = $this->throttle->lockedFor($request, $identifier)) {
            return $this->lockedResponse($request, $seconds);
        }

        $user = User::findForLogin($identifier);
        // Hash against a dummy value for unknown users so response time does not reveal which accounts exist.
        $valid = Hash::check($credentials['password'], $user?->password ?? self::TIMING_GUARD_HASH) && $user !== null;

        if (! $valid) {
            $this->throttle->hit($request, $identifier);
            SecurityLog::record('login_failed', $user, $request, ['identifier' => mb_substr($identifier, 0, 3).'***']);

            if ($seconds = $this->throttle->lockedFor($request, $identifier)) {
                SecurityLog::record('login_locked', $user, $request);

                return $this->lockedResponse($request, $seconds);
            }

            return $this->failedResponse($request, 'These credentials do not match our records.', $this->throttle->remainingAttempts($request, $identifier));
        }

        if (! $user->isActive()) {
            SecurityLog::record('login_blocked_inactive', $user, $request);

            return $this->failedResponse($request, 'This account is not active.');
        }

        $this->throttle->clear($request, $identifier);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('auth.two_factor', [
                'user_id' => $user->id,
                'remember' => $request->boolean('remember'),
                'expires_at' => now()->addMinutes(5)->timestamp,
            ]);

            return $this->redirectResponse($request, route('two-factor.challenge'));
        }

        self::completeLogin($request, $user, $request->boolean('remember'));

        return $this->redirectResponse($request, redirect()->intended(route('dashboard'))->getTargetUrl());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user) {
            SecurityLog::record('logout', $user, $request);
        }

        return redirect()->route('login');
    }

    public static function completeLogin(Request $request, User $user, bool $remember): void
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        SecurityLog::record('login', $user, $request);
    }

    private function lockedResponse(Request $request, int $seconds): JsonResponse|RedirectResponse
    {
        $message = 'Too many failed attempts. Try again in '.gmdate('i:s', $seconds).'.';

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'retry_after' => $seconds], 429)
                ->header('Retry-After', (string) $seconds);
        }

        return back()->withInput($request->only('login'))->withErrors(['login' => $message]);
    }

    private function failedResponse(Request $request, string $message, ?int $remaining = null): JsonResponse|RedirectResponse
    {
        if ($remaining !== null && $remaining <= 2) {
            $message .= ' '.$remaining.' '.($remaining === 1 ? 'attempt' : 'attempts').' left.';
        }

        if ($request->expectsJson()) {
            return Ajax::error('Validation failed', 422, ['login' => [$message]]);
        }

        return back()->withInput($request->only('login', 'remember'))->withErrors(['login' => $message]);
    }

    private function redirectResponse(Request $request, string $url): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? Ajax::success(null, ['redirect' => $url]) : redirect()->to($url);
    }
}
