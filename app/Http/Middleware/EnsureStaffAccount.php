<?php

namespace App\Http\Middleware;

use App\Modules\Portal\Services\PortalAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Keeps investor and customer portal accounts out of the internal application. */
class EnsureStaffAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->isStaff()) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Not found.'], 404)
                : redirect()->to(PortalAccess::homeRoute($user));
        }

        return $next($request);
    }
}
