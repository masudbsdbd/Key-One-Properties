<?php

namespace App\Support\Attachments;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps the short type used in attachment URLs to the owning model and the policy
 * abilities that guard viewing and changing its files.
 */
class AttachableRegistry
{
    /** @var array<string, array{model: class-string<Model>, view: string, manage: string}> */
    private static array $types = [];

    /**
     * @param  class-string<Model>  $model
     */
    public static function register(string $type, string $model, string $view = 'view', string $manage = 'update'): void
    {
        self::$types[$type] = ['model' => $model, 'view' => $view, 'manage' => $manage];
    }

    /**
     * @return array{model: class-string<Model>, view: string, manage: string}|null
     */
    public static function get(string $type): ?array
    {
        return self::$types[$type] ?? null;
    }

    public static function typeFor(Model $model): ?string
    {
        foreach (self::$types as $type => $config) {
            if ($model instanceof $config['model']) {
                return $type;
            }
        }

        return null;
    }
}
