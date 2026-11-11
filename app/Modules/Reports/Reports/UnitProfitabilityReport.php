<?php

namespace App\Modules\Reports\Reports;

use App\Models\FlatProfitCalculation;
use App\Models\Project;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** The latest approved profit calculation of each unit: what it sold for, what it cost, and who gets the profit. */
class UnitProfitabilityReport extends BaseReport
{
    public function key(): string
    {
        return 'unit-profitability';
    }

    public function name(): string
    {
        return 'Unit Profitability';
    }

    public function group(): string
    {
        return 'investors';
    }

    public function basis(): string
    {
        return 'Latest approved profit calculation of each unit; cost is unit, shared and other cost as of the date shown';
    }

    public function filters(): array
    {
        return ['project'];
    }

    public function search(): array
    {
        return ['flat_profit_calculations.calculation_no'];
    }

    public function columns(): array
    {
        return ['Unit' => 'text', 'Cost As Of' => 'date', 'Revenue' => 'money', 'Cost' => 'money', 'Net Profit' => 'money', 'Margin %' => 'number', 'Investors' => 'money', 'Company' => 'money'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = FlatProfitCalculation::query()->where('flat_profit_calculations.status', 'approved')
            ->whereIn('flat_profit_calculations.id', FlatProfitCalculation::query()->where('status', 'approved')->selectRaw('MAX(id)')->groupBy('flat_id'))
            ->whereIn('flat_profit_calculations.project_id', Project::query()->visibleTo($user)->select('projects.id'))
            ->with(['flat:id,flat_no', 'project:id,code'])->select('flat_profit_calculations.*')
            ->selectRaw('(SELECT COALESCE(SUM(a.profit_amount), 0) FROM investor_profit_allocations a WHERE a.profit_calculation_id = flat_profit_calculations.id) AS investors_amount');
        if ($project = self::project($request, $user)) {
            $query->where('flat_profit_calculations.project_id', $project->id);
        }

        return $query->orderBy('flat_profit_calculations.project_id')->orderBy('flat_profit_calculations.flat_id');
    }

    public function row(mixed $c): array
    {
        $investors = Money::amount((string) $c->investors_amount);

        return [$c->project->code.' · '.$c->flat->flat_no, $c->basis_snapshot['as_of'] ?? null, $c->revenue_amount, self::cost($c), $c->net_profit_amount,
            (float) $c->revenue_amount > 0 ? round((float) $c->net_profit_amount / (float) $c->revenue_amount * 100, 1) : null,
            $investors, Money::amount(Money::sub((string) $c->net_profit_amount, $investors))];
    }

    /** Unit-tagged, shared and other approved cost together. */
    private static function cost(mixed $c): string
    {
        return Money::amount(Money::add((string) $c->direct_cost_amount, (string) $c->shared_cost_amount, (string) $c->other_approved_cost_amount));
    }

    public function totals(Builder $query): ?array
    {
        $rows = (clone $query)->reorder()->get();
        $sum = fn (string $field) => Money::amount(Money::add('0', ...$rows->map(fn ($r) => (string) $r->{$field})->all()));
        $net = $sum('net_profit_amount');

        return ['Total', null, $sum('revenue_amount'), Money::amount(Money::add($sum('direct_cost_amount'), $sum('shared_cost_amount'), $sum('other_approved_cost_amount'))), $net, null,
            $sum('investors_amount'), Money::amount(Money::sub($net, $sum('investors_amount')))];
    }
}
