<?php

namespace App\Modules\Accounting\Services;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Modules\Accounting\Support\AccountingSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Periods
{
    /** The month a date falls in; the fiscal year and its twelve months are created on first use. */
    public static function for(CarbonInterface $date): AccountingPeriod
    {
        $day = $date->toDateString();
        $find = fn () => AccountingPeriod::query()->where('start_date', '<=', $day)->where('end_date', '>=', $day)->first();

        if ($period = $find()) {
            return $period;
        }

        try {
            DB::transaction(fn () => self::createYear(CarbonImmutable::parse($day)));
        } catch (UniqueConstraintViolationException) {
            // Another request created the year first.
        }

        return $find() ?? throw ValidationException::withMessages(['accounting_date' => 'No accounting period covers '.$day.'.']);
    }

    /** Throws when the month is locked or closed. Pass $lock to hold the row for the posting. */
    public static function ensureOpen(CarbonInterface $date, bool $lock = false, string $field = 'accounting_date'): AccountingPeriod
    {
        $period = self::for($date);
        if ($lock) {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);
        }
        if (! $period->isOpen()) {
            throw ValidationException::withMessages([$field => $period->name.' is '.strtolower(AccountingPeriod::STATUSES[$period->status]).'. Use a date in an open month.']);
        }

        return $period;
    }

    public static function yearStart(CarbonImmutable $date): CarbonImmutable
    {
        $month = AccountingSettings::fiscalStartMonth();
        $start = $date->setDate($date->year, $month, 1)->startOfDay();

        return $start->greaterThan($date) ? $start->subYear() : $start;
    }

    private static function createYear(CarbonImmutable $date): void
    {
        $start = self::yearStart($date);
        $end = $start->addYear()->subDay();
        $year = FiscalYear::query()->create([
            'name' => $start->year === $end->year ? 'FY '.$start->year : 'FY '.$start->year.'–'.$end->format('y'),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);
        for ($month = $start; $month->lessThanOrEqualTo($end); $month = $month->addMonth()) {
            AccountingPeriod::query()->create([
                'fiscal_year_id' => $year->id,
                'name' => $month->format('M Y'),
                'start_date' => $month->toDateString(),
                'end_date' => $month->endOfMonth()->toDateString(),
            ]);
        }
    }
}
