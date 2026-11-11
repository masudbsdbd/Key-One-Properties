<?php

namespace App\Modules\Reports;

use App\Models\User;
use App\Modules\Reports\Reports as R;

class ReportRegistry
{
    /** @var array<int, class-string<BaseReport>> */
    private const REPORTS = [
        R\DailyCashBookReport::class,
        R\ProjectSummaryReport::class,
        R\ProjectBudgetReport::class,
        R\ProjectCostReport::class,
        R\BudgetVsActualReport::class,
        R\StockValuationReport::class,
        R\SupplierAgingReport::class,
        R\ContractorDuesReport::class,
        R\ExpenseSummaryReport::class,
        R\UnitStatusReport::class,
        R\SalesRegisterReport::class,
        R\CollectionReceiptsReport::class,
        R\CustomerOutstandingReport::class,
        R\InstallmentAgingReport::class,
        R\CollectionForecastReport::class,
        R\InvestorPositionReport::class,
        R\UnitProfitabilityReport::class,
        R\AuditActivityReport::class,
    ];

    /** @return array<string, BaseReport> */
    public static function all(): array
    {
        return collect(self::REPORTS)->map(fn ($c) => app($c))->keyBy(fn (BaseReport $r) => $r->key())->all();
    }

    public static function find(string $key): ?BaseReport
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<string, array<int, BaseReport>> group => reports the user may run */
    public static function catalog(User $user): array
    {
        return collect(self::all())->filter(fn (BaseReport $r) => $r->allowed($user))
            ->groupBy(fn (BaseReport $r) => $r->group())->sortBy(fn ($v, $k) => array_search($k, array_keys(BaseReport::GROUPS), true))
            ->map(fn ($items) => $items->values()->all())->all();
    }
}
