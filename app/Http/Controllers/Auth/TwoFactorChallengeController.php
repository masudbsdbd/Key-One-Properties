<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\SecurityLog;
use App\Services\Auth\TwoFactor;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, TwoFactor $twoFactor): JsonResponse|RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return $request->expectsJson()
                ? Ajax::success(null, ['redirect' => route('login')])
                : redirect()->route('login');
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:12', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
        ]);

        $key = '2fa:challenge:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, config('keyone.login.max_attempts'))) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json(['success' => false, 'message' => 'Too many attempts. Try again in '.gmdate('i:s', $seconds).'.', 'retry_after' => $seconds], 429);
        }

        $valid = ! empty($data['recovery_code'])
            ? $twoFactor->useRecoveryCode($user, $data['recovery_code'])
            : $twoFactor->verify($user->two_factor_secret, (string) $data['code'], $user->id);

        if (! $valid) {
            RateLimiter::hit($key, config('keyone.login.decay_seconds'));
            SecurityLog::record('two_factor_failed', $user, $request);
            $field = ! empty($data['recovery_code']) ? 'recovery_code' : 'code';

            return Ajax::error('Validation failed', 422, [$field => ['The code is invalid.']]);
        }

        RateLimiter::clear($key);
        $remember = (bool) ($request->session()->pull('auth.two_factor')['remember'] ?? false);
        LoginController::completeLogin($request, $user, $remember);

        return Ajax::success(null, ['redirect' => redirect()->intended(route('dashboard'))->getTargetUrl()]);
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('auth.two_factor');

        if (! $pending || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('auth.two_factor');

            return null;
        }

        $user = User::query()->find($pending['user_id']);

        return $user && $user->isActive() && $user->hasTwoFactorEnabled() ? $user : null;
    }
}
