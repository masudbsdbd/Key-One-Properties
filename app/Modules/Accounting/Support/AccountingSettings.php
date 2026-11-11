<?php

namespace App\Modules\Accounting\Support;

use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class AccountingSettings
{
    public const ENABLED = 'accounting.enabled';

    public const START_DATE = 'accounting.start_date';

    public const FISCAL_START_MONTH = 'accounting.fiscal_start_month';

    /** Automatic posting from the other modules is on. */
    public static function enabled(): bool
    {
        return app(Settings::class)->get(self::ENABLED) === '1' && self::startDate() !== null;
    }

    /** First day of the books; earlier transactions are covered by the opening balance. */
    public static function startDate(): ?CarbonImmutable
    {
        $value = app(Settings::class)->get(self::START_DATE);

        return $value ? CarbonImmutable::parse($value)->startOfDay() : null;
    }

    public static function covers(CarbonInterface $date): bool
    {
        return self::enabled() && $date->toDateString() >= self::startDate()->toDateString();
    }

    public static function fiscalStartMonth(): int
    {
        $month = (int) app(Settings::class)->get(self::FISCAL_START_MONTH, '7');

        return $month >= 1 && $month <= 12 ? $month : 7;
    }
}
