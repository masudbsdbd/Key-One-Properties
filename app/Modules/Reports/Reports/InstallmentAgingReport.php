<?php

namespace App\Modules\Reports\Reports;

use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class InstallmentAgingReport extends BaseReport
{
    public function key(): string
    {
        return 'installment-aging';
    }

    public function name(): string
    {
        return 'Installment Aging';
    }

    public function group(): string
    {
        return 'collections';
    }

    public function basis(): string
    {
        return 'Open items of active plans; billed late fees included, waived excluded';
    }

    public function filters(): array
    {
        return ['project'];
    }

    public function columns(): array
    {
        return ['Customer' => 'text', 'Project' => 'text', 'Unit' => 'text', 'Installment' => 'text', 'Due Date' => 'date', 'Principal Due' => 'money', 'Late Fee Due' => 'money', 'Outstanding' => 'money', 'Days Overdue' => 'number', 'Bucket' => 'text'];
    }

    public function query(Request $request, User $user): Builder
    {
        $plans = InstallmentPlan::query()->visibleTo($user)->where('status', 'active');
        if ($project = self::project($request, $user)) {
            $plans->whereHas('sale', fn ($q) => $q->where('project_id', $project->id));
        }

        return InstallmentScheduleItem::query()->whereIn('plan_id', $plans->select('id'))->where('status', '!=', 'rescheduled')
            ->whereRaw('(principal_due + fee_due) > (principal_paid + fee_paid)')->with(['plan.customer:id,full_name', 'plan.sale.flat:id,flat_no', 'plan.sale.project:id,code'])
            ->orderBy('due_date')->orderBy('id');
    }

    public function row(mixed $i): array
    {
        $days = max(0, (int) $i->due_date->diffInDays(Dates::today(), false));

        return [$i->plan->customer->full_name, $i->plan->sale->project->code, $i->plan->sale->flat->flat_no, '#'.$i->sequence_no, $i->due_date,
            $i->principalOutstanding(), $i->feeOutstanding(), $i->outstanding(), $days, AgingBuckets::label($days)];
    }

    public function totals(Builder $query): ?array
    {
        $row = (clone $query)->reorder()->selectRaw('COALESCE(SUM(principal_due + fee_due - principal_paid - fee_paid),0) AS o')->toBase()->first();

        return ['Total', null, null, null, null, null, null, Money::amount((string) $row->o), null, null];
    }
}
