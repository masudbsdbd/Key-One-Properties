<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final class Dates
{
    /**
     * The local calendar date (display timezone) at midnight in the app timezone, the same form date columns are
     * read in, so comparisons and day counts against them are exact.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now(config('app.display_timezone'))->toDateString(), config('app.timezone'));
    }

    /** Whole days from today to a date column value; negative once the date has passed. */
    public static function daysUntil(CarbonInterface $date): int
    {
        return (int) self::today()->diffInDays(CarbonImmutable::parse($date->toDateString(), config('app.timezone')), false);
    }

    /** "12 days left", "Due today" or "3 days late" for a date column value. */
    public static function countdown(CarbonInterface $date): string
    {
        $days = self::daysUntil($date);
        $count = abs($days).' '.Str::plural('day', abs($days));

        return match (true) {
            $days < 0 => $count.' late',
            $days === 0 => 'Due today',
            default => $count.' left',
        };
    }
}
