<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Notifications\PasswordResetRequested;
use App\Services\Auth\SecurityLog;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Users who forget their password send a request; an administrator sets a new one.
 */
class PasswordResetController extends Controller
{
    public const MESSAGE = 'Your request has been sent to the administrator.';

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:191']]);

        $key = 'password-request:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json(['success' => false, 'message' => 'Too many requests. Try again in '.gmdate('i:s', $seconds).'.', 'retry_after' => $seconds], 429);
        }
        RateLimiter::hit($key, 900);

        $user = User::findForLogin($data['login']);

        if ($user && $user->isActive()) {
            $created = DB::transaction(function () use ($user, $request) {
                $pending = PasswordResetRequest::query()->where('user_id', $user->id)->where('status', 'pending')->lockForUpdate()->first();
                if ($pending) {
                    $pending->touch();

                    return null;
                }

                return PasswordResetRequest::create([
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'requested_ip' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
                ]);
            });

            if ($created) {
                Notification::send(self::approvers($user), new PasswordResetRequested($created));
                SecurityLog::record('password_reset_requested', $user, $request);
            }
        }

        // Same answer whether or not the account exists.
        return Ajax::success(self::MESSAGE);
    }

    /**
     * Active users allowed to reset this user's password.
     *
     * @return Collection<int, User>
     */
    public static function approvers(User $user)
    {
        return User::query()->active()->whereKeyNot($user->id)
            ->where(fn ($q) => $q->permission('users.reset_password')->orWhereHas('roles', fn ($r) => $r->where('name', config('keyone.super_admin_role'))))
            ->get()
            ->filter(fn (User $admin) => $admin->can('resetPassword', $user))
            ->values();
    }
}
