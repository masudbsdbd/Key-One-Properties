<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Adds a random, unguessable `public_id` used in URLs and API payloads
 * in place of the internal auto-increment key.
 */
trait HasPublicId
{
    public const PUBLIC_ID_LENGTH = 16;

    public static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = static::newPublicId();
            }
        });
    }

    public static function newPublicId(): string
    {
        return Str::lower(Str::random(self::PUBLIC_ID_LENGTH));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public static function findByPublicId(?string $publicId): ?static
    {
        return $publicId ? static::query()->where('public_id', $publicId)->first() : null;
    }

    public static function findByPublicIdOrFail(?string $publicId): static
    {
        return static::query()->where('public_id', (string) $publicId)->firstOrFail();
    }
}
