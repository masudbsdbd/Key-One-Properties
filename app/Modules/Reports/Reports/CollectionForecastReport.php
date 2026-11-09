<?php

namespace App\Modules\Reports\Reports;

use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** What customers still owe, month by month by due date: past months are overdue, later months are expected. */
class CollectionForecastReport extends BaseReport
{
    public function key(): string
    {
        return 'collection-forecast';
    }

    public function name(): string
    {
        return 'Collection Forecast';
    }

    public function group(): string
    {
        return 'collections';
    }

    public function basis(): string
    {
        return 'Open installments of active plans by due month; billed late fees included';
    }

    public function columns(): array
    {
        return ['Month' => 'text', 'State' => 'text', 'Installments' => 'number', 'Customers' => 'number', 'Principal Due' => 'money', 'Late Fee Due' => 'money', 'Outstanding' => 'money'];
    }

    public function query(Request $request, User $user): Builder
    {
        $plans = InstallmentPlan::query()->visibleTo($user)->where('status', 'active');
        if ($project = self::project($request, $user)) {
            $plans->whereHas('sale', fn ($q) => $q->where('project_id', $project->id));
        }
        $query = InstallmentScheduleItem::query()->from('installment_schedule_items as i')->join('installment_plans as p', 'p.id', '=', 'i.plan_id')
            ->whereIn('i.plan_id', $plans->select('id'))->where('i.status', '!=', 'rescheduled')->whereRaw('i.principal_due + i.fee_due - i.principal_paid - i.fee_paid > 0');

        return $this->inPeriod($query, $request, 'i.due_date')
            ->selectRaw("DATE_FORMAT(i.due_date, '%Y-%m') AS month, COUNT(*) AS items, COUNT(DISTINCT p.customer_id) AS customers")
            ->selectRaw('SUM(i.principal_due - i.principal_paid) AS principal, SUM(i.fee_due - i.fee_paid) AS fee')
            ->groupByRaw("DATE_FORMAT(i.due_date, '%Y-%m')")->orderBy('month');
    }

    public function row(mixed $r): array
    {
        $month = Carbon::createFromFormat('Y-m-d', $r->month.'-01');
        $current = now(config('app.display_timezone'))->startOfMonth();

        return [$month->format('M Y'), $month->lt($current) ? 'Overdue' : ($month->eq($current) ? 'This month' : 'Expected'), (int) $r->items, (int) $r->customers,
            Money::amount((string) $r->principal), Money::amount((string) $r->fee), Money::amount(Money::add((string) $r->principal, (string) $r->fee))];
    }

    public function totals(Builder $query): ?array
    {
        $rows = (clone $query)->reorder()->get();
        $principal = Money::add('0', ...$rows->map(fn ($r) => (string) $r->principal)->all());
        $fee = Money::add('0', ...$rows->map(fn ($r) => (string) $r->fee)->all());

        return ['Total', null, (int) $rows->sum('items'), null, Money::amount($principal), Money::amount($fee), Money::amount(Money::add($principal, $fee))];
    }
}
