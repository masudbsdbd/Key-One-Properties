<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Auth\SecurityLog;
use App\Services\Auth\TwoFactor;
use App\Support\Ajax;
use App\Support\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($s) => (object) [
                'current' => $s->id === $request->session()->getId(),
                'ip' => $s->ip_address,
                'agent' => Str::limit((string) $s->user_agent, 80),
                'last_active' => now()->setTimestamp($s->last_activity),
            ]);

        return view('profile.edit', ['user' => $user, 'sessions' => $sessions]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->merge([
            'email' => $request->filled('email') ? mb_strtolower(trim($request->input('email'))) : null,
            'mobile' => User::normalizeMobile($request->input('mobile')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:191', 'required_without:mobile', Rule::unique('users')->ignore($user->id)],
            'mobile' => ['nullable', 'string', 'regex:/^\+?\d{10,15}$/', 'required_without:email', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($data);

        return Ajax::success('Profile updated.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'different:current_password'],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $data['password'], 'password_changed_at' => now(), 'must_change_password' => false])->save();
        $this->destroyOtherSessions($request);
        SecurityLog::record('password_changed', $user, $request);

        return Ajax::success('Password updated.');
    }

    public function enableTwoFactor(Request $request, TwoFactor $twoFactor, Branding $branding): JsonResponse
    {
        $user = $request->user();
        if ($user->hasTwoFactorEnabled()) {
            return Ajax::error('Two-factor authentication is already enabled.', 409);
        }

        $secret = $twoFactor->generateSecret();
        $request->session()->put('two_factor.pending_secret', encrypt($secret));

        return Ajax::success(null, [
            'qr' => $twoFactor->qrSvg($user, $secret, $branding->name()),
            'secret' => trim(chunk_split($secret, 4, ' ')),
        ]);
    }

    public function confirmTwoFactor(Request $request, TwoFactor $twoFactor): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);

        $pending = $request->session()->get('two_factor.pending_secret');
        if (! $pending) {
            return Ajax::error('Start the setup again.', 409);
        }

        $secret = decrypt($pending);
        if (! $twoFactor->verify($secret, $request->string('code')->value())) {
            return Ajax::error('Validation failed', 422, ['code' => ['The code is invalid.']]);
        }

        $codes = $twoFactor->recoveryCodes();
        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes->all(),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->forget('two_factor.pending_secret');
        SecurityLog::record('two_factor_enabled', $user, $request);

        return Ajax::success('Two-factor authentication enabled.', ['recovery_codes' => $codes->all()]);
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactor $twoFactor): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return Ajax::error('Two-factor authentication is not enabled.', 409);
        }

        $codes = $twoFactor->recoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes->all()])->save();
        SecurityLog::record('two_factor_recovery_regenerated', $user, $request);

        return Ajax::success('New recovery codes generated.', ['recovery_codes' => $codes->all()]);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();

        if ($user->two_factor_required) {
            return Ajax::error('Two-factor authentication is required for your account.', 403);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        SecurityLog::record('two_factor_disabled', $user, $request);

        return Ajax::success('Two-factor authentication disabled.');
    }

    public function logoutOtherSessions(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $count = $this->destroyOtherSessions($request);
        SecurityLog::record('sessions_revoked', $request->user(), $request, ['count' => $count]);

        return Ajax::success('Signed out of other devices.');
    }

    private function destroyOtherSessions(Request $request): int
    {
        $user = $request->user();
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }
}
