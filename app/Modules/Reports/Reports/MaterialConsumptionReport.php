<?php

namespace App\Modules\Reports\Reports;

use App\Models\Project;
use App\Models\StockIssueLine;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** What each project used of each material: issued, returned to the store, and the cost that stayed on the project. */
class MaterialConsumptionReport extends BaseReport
{
    private const RETURNED = 'ROUND(stock_issue_lines.unit_cost * stock_issue_lines.returned_qty, 2)';

    public function key(): string
    {
        return 'material-consumption';
    }

    public function name(): string
    {
        return 'Material Consumption';
    }

    public function group(): string
    {
        return 'inventory';
    }

    public function basis(): string
    {
        return 'Posted issues by issue date, less what was returned to the store';
    }

    public function columns(): array
    {
        return ['Project' => 'text', 'Material' => 'text', 'Unit' => 'text', 'Issued' => 'qty', 'Returned' => 'qty', 'Used' => 'qty', 'Cost' => 'money', 'Avg Cost' => 'money'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = StockIssueLine::query()->join('stock_issues', 'stock_issues.id', '=', 'stock_issue_lines.stock_issue_id')
            ->join('materials', 'materials.id', '=', 'stock_issue_lines.material_id')->leftJoin('material_units as units', 'units.id', '=', 'materials.unit_id')
            ->leftJoin('projects', 'projects.id', '=', 'stock_issues.project_id')
            ->where('stock_issues.status', 'posted')
            ->where(fn (Builder $q) => $q->whereNull('stock_issues.project_id')->orWhereIn('stock_issues.project_id', Project::query()->visibleTo($user)->select('projects.id')))
            ->groupBy('projects.code', 'materials.name', 'units.code')
            ->selectRaw('projects.code AS project_code, materials.name AS material_name, units.code AS unit_code, SUM(stock_issue_lines.quantity) AS issued_qty,
                SUM(stock_issue_lines.returned_qty) AS returned_qty, SUM(stock_issue_lines.total_value) - SUM('.self::RETURNED.') AS cost');
        if ($project = self::project($request, $user)) {
            $query->where('stock_issues.project_id', $project->id);
        }

        return $this->inPeriod($query, $request, 'stock_issues.issued_at')->orderBy('projects.code')->orderByDesc('cost');
    }

    public function row(mixed $r): array
    {
        $used = (float) $r->issued_qty - (float) $r->returned_qty;

        return [$r->project_code ?? 'Company', $r->material_name, $r->unit_code, $r->issued_qty, $r->returned_qty, $used, Money::amount((string) $r->cost),
            $used > 0 ? Money::amount((string) ((float) $r->cost / $used)) : null];
    }

    public function totals(Builder $query): ?array
    {
        $rows = (clone $query)->reorder()->get();

        return ['Total', null, null, null, null, null, Money::amount(Money::add('0', ...$rows->map(fn ($r) => (string) $r->cost)->all())), null];
    }
}
