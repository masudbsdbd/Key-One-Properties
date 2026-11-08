<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Locks sign-in for a client IP and for an identifier after repeated failures.
 * Both keys are checked so rotating IPs or usernames does not bypass the lock.
 */
class LoginThrottle
{
    public function maxAttempts(): int
    {
        return config('keyone.login.max_attempts');
    }

    public function decaySeconds(): int
    {
        return config('keyone.login.decay_seconds');
    }

    public function ipKey(Request $request): string
    {
        return 'login:ip:'.$request->ip();
    }

    public function identifierKey(?string $identifier): ?string
    {
        $identifier = mb_strtolower(trim((string) $identifier));

        return $identifier === '' ? null : 'login:id:'.hash('sha256', $identifier);
    }

    /**
     * Seconds until sign-in is allowed again (0 when not locked).
     */
    public function lockedFor(Request $request, ?string $identifier = null): int
    {
        $seconds = 0;

        foreach (array_filter([$this->ipKey($request), $this->identifierKey($identifier)]) as $key) {
            if (RateLimiter::tooManyAttempts($key, $this->maxAttempts())) {
                $seconds = max($seconds, RateLimiter::availableIn($key));
            }
        }

        return $seconds;
    }

    public function hit(Request $request, ?string $identifier): void
    {
        foreach (array_filter([$this->ipKey($request), $this->identifierKey($identifier)]) as $key) {
            RateLimiter::hit($key, $this->decaySeconds());
        }
    }

    public function remainingAttempts(Request $request, ?string $identifier): int
    {
        return collect(array_filter([$this->ipKey($request), $this->identifierKey($identifier)]))
            ->map(fn (string $key) => RateLimiter::remaining($key, $this->maxAttempts()))
            ->min();
    }

    public function clear(Request $request, ?string $identifier): void
    {
        foreach (array_filter([$this->ipKey($request), $this->identifierKey($identifier)]) as $key) {
            RateLimiter::clear($key);
        }
    }
}
