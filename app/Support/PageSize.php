<?php

namespace App\Support;

/**
 * Rows-per-page choices for every list. The choices and the default come from Settings.
 */
class PageSize
{
    public const OPTIONS = [10, 25, 50, 100, 500, 1000, 3000];

    public const DEFAULT = 10;

    public const MAX = 5000;

    public const MAX_OPTIONS = 12;

    /** @return array<int, int> */
    public static function options(): array
    {
        $stored = json_decode((string) app(Settings::class)->get('tables.page_sizes'), true);
        $sizes = self::clean(is_array($stored) ? $stored : []);

        return $sizes ?: self::OPTIONS;
    }

    public static function default(): int
    {
        $options = self::options();
        $default = (int) app(Settings::class)->get('tables.default_page_size', (string) self::DEFAULT);

        return in_array($default, $options, true) ? $default : $options[0];
    }

    /** The requested size when it is one of the choices, otherwise the default. */
    public static function resolve(mixed $requested): int
    {
        $size = is_numeric($requested) ? (int) $requested : 0;

        return in_array($size, self::options(), true) ? $size : self::default();
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, int>
     */
    public static function clean(array $values): array
    {
        $sizes = collect($values)->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v)
            ->filter(fn (int $v) => $v >= 1 && $v <= self::MAX)->unique()->sort()->values()->all();

        return array_slice($sizes, 0, self::MAX_OPTIONS);
    }
}
