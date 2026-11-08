<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\SecurityLog;
use App\Support\Ajax;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:191']]);

        $key = 'password-reset:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return Ajax::error('Too many requests. Try again later.', 429);
        }
        RateLimiter::hit($key, 900);

        Password::sendResetLink(['email' => mb_strtolower($data['email'])]);

        // Same response whether or not the account exists.
        return Ajax::success('If the email belongs to an account, a reset link has been sent.');
    }

    public function edit(string $token): View
    {
        return view('auth.reset-password', ['token' => $token]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            ['email' => mb_strtolower($data['email'])] + $data,
            function (User $user, string $password) use ($request) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'password_changed_at' => now(),
                ])->save();

                event(new PasswordReset($user));
                SecurityLog::record('password_reset', $user, $request);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return Ajax::error('Validation failed', 422, ['email' => [__($status)]]);
        }

        return Ajax::success('Password updated. Please sign in.', ['redirect' => route('login')]);
    }
}
