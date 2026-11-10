<?php

namespace App\Support;

use Carbon\CarbonImmutable;

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
}
