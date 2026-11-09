<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Plain `throttle:N,M` keyed per user (or IP) and route, so heavy endpoints such as searchable dropdowns
 * do not spend the budget of exports or downloads. Named limiters keep their own keys.
 */
class ThrottlePerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        return sha1(parent::resolveRequestSignature($request).'|'.($request->route()?->getName() ?? $request->route()?->uri()));
    }
}
