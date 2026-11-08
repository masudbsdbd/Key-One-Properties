<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After an administrator sets a password, the user must choose their own before continuing.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs('profile.*', 'logout')) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Change your password to continue.'], 403);
            }

            return redirect()->to(route('profile.edit').'#security')->with('error', 'Change your password to continue.');
        }

        return $next($request);
    }
}
