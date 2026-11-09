<?php

namespace App\Support;

use App\Models\User;

/**
 * Per-user UI choices kept in users.preferences. Only listed keys and values are accepted.
 */
class Preferences
{
    public const ALLOWED = [
        'flats.view' => ['table', 'cards'],
        'projects.view' => ['table', 'cards'],
    ];

    public static function get(?User $user, string $key): string
    {
        $value = $user?->preferences[$key] ?? null;

        return in_array($value, self::ALLOWED[$key] ?? [], true) ? $value : (self::ALLOWED[$key][0] ?? '');
    }

    public static function set(User $user, string $key, string $value): void
    {
        $user->forceFill(['preferences' => array_merge($user->preferences ?? [], [$key => $value])])->save();
    }
}
