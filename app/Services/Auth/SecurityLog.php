<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;

class SecurityLog
{
    /**
     * Record an authentication or security event in the activity log.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $event, ?User $user, Request $request, array $properties = []): void
    {
        $logger = activity('security')
            ->event($event)
            ->withProperties($properties + [
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]);

        if ($user) {
            $logger->causedBy($user)->performedOn($user);
        }

        $logger->log($event);
    }
}
