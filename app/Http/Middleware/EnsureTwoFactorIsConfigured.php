<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->two_factor_required && ! $user->hasTwoFactorEnabled()
            && ! $request->routeIs('profile.*', 'investor.profile', 'customer.profile', 'logout')) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Two-factor authentication must be set up first.'], 403);
            }

            return redirect()->to(($user->isStaff() ? route('profile.edit') : route($user->account_type.'.profile')).'#security')->with('error', 'Set up two-factor authentication to continue.');
        }

        return $next($request);
    }
}
