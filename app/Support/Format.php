<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Format
{
    /** PDF fonts have no taka glyph; PDF rendering switches to "Tk". */
    public static bool $plainSymbol = false;

    /**
     * Money with South Asian digit grouping, e.g. ৳ 1,23,45,678.00.
     */
    /** "৳ 7,50,000" for whole amounts, "৳ 7,50,000.53" when there are paisa. */
    public static function money(string|float|int|null $amount, bool $decimals = true): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $negative = (float) $amount < 0;
        [$whole, $fraction] = array_pad(explode('.', number_format(abs((float) $amount), 2, '.', '')), 2, '00');

        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $grouped = $rest !== '' ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3 : $last3;

        $symbol = config('app.currency') === 'BDT' ? (self::$plainSymbol ? 'Tk' : '৳') : config('app.currency');

        return ($negative ? '-' : '').$symbol.' '.$grouped.($decimals && $fraction !== '00' ? '.'.$fraction : '');
    }

    /** Quantity without trailing zeros, e.g. 12.500 -> 12.5. */
    public static function qty(string|float|int|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $formatted = rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }

    public static function date(?CarbonInterface $date): string
    {
        return $date ? $date->format('d M Y') : '—';
    }

    public static function datetime(?CarbonInterface $date): string
    {
        return $date ? $date->timezone(config('app.display_timezone'))->format('d M Y, h:i A') : '—';
    }

    public static function percent(string|float|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1), '0'), '.').'%';
    }

    public static function bytes(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : max(1, round($bytes / 1024)).' KB';
    }
}
