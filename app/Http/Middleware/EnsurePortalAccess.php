<?php

namespace App\Http\Middleware;

use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request into /investor or /customer only for a portal account of that type with a usable link
 * and the portal permission. The resolved identity is bound for the controllers; nothing comes from input.
 */
class EnsurePortalAccess
{
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        $user = $request->user();
        if ($user && $user->isStaff()) {
            abort(404);
        }
        if ($user && $user->account_type !== $portal) {
            return $request->expectsJson() ? response()->json(['success' => false, 'message' => 'Not found.'], 404) : redirect()->to(PortalAccess::homeRoute($user));
        }

        $record = $portal === 'investor' ? PortalAccess::investorFor($user) : PortalAccess::customerFor($user);
        if (! $record || ! $user->can($portal.'.portal.view')) {
            PortalAccess::audit($user, $portal, 'enter', null, 'denied', $request);
            abort(403, 'Portal access is not available for this account.');
        }

        app()->instance(PortalContext::class, new PortalContext($user, $portal, $portal === 'investor' ? $record : null, $portal === 'customer' ? $record : null));

        return $next($request);
    }
}
