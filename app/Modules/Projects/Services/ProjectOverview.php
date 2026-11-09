<?php

namespace App\Modules\Projects\Services;

use App\Models\CustomerPayment;
use App\Models\CustomerStatementEntry;
use App\Models\Flat;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use App\Support\Costing\CostAttribution;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * A project at a glance: schedule against progress, units, money in and cost. Each part is null when the
 * user may not see it, so the page simply leaves that card out.
 */
class ProjectOverview
{
    public static function for(Project $project, User $user): array
    {
        $project->loadMissing('stages');
        $today = CarbonImmutable::now(config('app.display_timezone'))->startOfDay();

        return [
            'progress' => $project->overallProgress(),
            'stages_done' => $project->stages->where('status', 'completed')->count(),
            'stages' => $project->stages->count(),
            'timeline' => self::timeline($project, $today),
            'units' => $user->can('flats.view') ? self::units($project) : null,
            'sales' => $user->can('sales.sale.view') ? self::sales($project, $user, $today) : null,
            'cost' => Gate::forUser($user)->allows('construction:cost-view', $project) ? self::cost($project) : null,
            'milestone' => $user->can('construction.view')
                ? Milestone::query()->where('project_id', $project->id)->where('status', 'pending')->orderBy('planned_at')->first(['title', 'planned_at']) : null,
            'updates' => $project->progressUpdates()->with(['stage:id,name', 'recorder:id,name'])->latest('report_date')->latest('id')->limit(3)->get(),
        ];
    }

    private static function timeline(Project $project, CarbonImmutable $today): array
    {
        $start = $project->start_date ? CarbonImmutable::parse($project->start_date)->startOfDay() : null;
        $target = $project->target_completion_date ? CarbonImmutable::parse($project->target_completion_date)->startOfDay() : null;
        $done = $project->actual_completion_date ? CarbonImmutable::parse($project->actual_completion_date) : null;
        if (! $start || ! $target || $target->lte($start)) {
            return ['state' => $done ? 'completed' : 'unscheduled', 'time_pct' => null, 'days_left' => null, 'start' => $start, 'target' => $target, 'done' => $done];
        }
        $total = $start->diffInDays($target);
        $elapsed = max(0, min($total, $start->diffInDays($today, false)));
        $timePct = round($elapsed / $total * 100, 1);
        $daysLeft = (int) $today->diffInDays($target, false);
        $progress = $project->overallProgress();

        $state = match (true) {
            $done !== null || $project->status === 'completed' => 'completed',
            $today->lt($start) => 'not_started',
            $daysLeft < 0 => 'late',
            $progress + 10 < $timePct => 'behind',
            default => 'on_track',
        };

        return ['state' => $state, 'time_pct' => $timePct, 'days_left' => $daysLeft, 'start' => $start, 'target' => $target, 'done' => $done,
            'day' => (int) $elapsed, 'total_days' => (int) $total];
    }

    private static function units(Project $project): array
    {
        $counts = Flat::query()->where('project_id', $project->id)->selectRaw('status, COUNT(*) AS n, SUM(list_price) AS v')->groupBy('status')->get()->keyBy('status');
        $n = fn ($s) => (int) ($counts[$s]->n ?? 0);

        return ['total' => (int) $counts->sum('n'), 'sold' => $n('sold'), 'booked' => $n('booked'), 'available' => $n('available'), 'not_released' => $n('not_released'),
            'other' => $n('unavailable') + $n('maintenance'), 'available_value' => Money::amount((string) ($counts['available']->v ?? 0))];
    }

    private static function sales(Project $project, User $user, CarbonImmutable $today): array
    {
        $sales = FlatSale::query()->where('project_id', $project->id)->whereIn('status', FlatSale::ACTIVE);
        $value = (string) (clone $sales)->sum('net_contract_value');
        $collected = $user->can('collections.payment.view') ? (string) CustomerPayment::query()->where('project_id', $project->id)->where('status', 'posted')->sum('amount') : null;
        $receivable = $user->can('collections.payment.view') ? (string) (CustomerStatementEntry::query()
            ->where(fn ($q) => $q->whereIn('sale_id', (clone $sales)->select('id'))->orWhere(fn ($b) => $b->whereNull('sale_id')->whereIn('booking_id', (clone $sales)->whereNotNull('booking_id')->select('booking_id'))))
            ->selectRaw('SUM(charge_amount - credit_amount) AS b')->value('b') ?? 0) : null;
        $overdue = $user->can('collections.plan.view') ? (string) (InstallmentScheduleItem::query()->where('status', '!=', 'rescheduled')->whereDate('due_date', '<', $today)
            ->whereIn('plan_id', InstallmentPlan::query()->where('status', 'active')->whereIn('sale_id', (clone $sales)->select('id'))->select('id'))
            ->selectRaw('SUM(GREATEST(principal_due + fee_due - principal_paid - fee_paid, 0)) AS o')->value('o') ?? 0) : null;

        return ['count' => (clone $sales)->count(), 'value' => Money::amount($value), 'collected' => $collected !== null ? Money::amount($collected) : null,
            'collected_pct' => $collected !== null && (float) $value > 0 ? round((float) $collected / (float) $value * 100, 1) : null,
            'receivable' => $receivable !== null ? Money::amount($receivable) : null, 'overdue' => $overdue !== null ? Money::amount($overdue) : null];
    }

    private static function cost(Project $project): array
    {
        $byCategory = CostAttribution::totals($project);
        $spent = Money::amount(Money::add('0', ...array_values($byCategory)));
        $budget = $project->estimated_budget !== null ? (string) $project->estimated_budget : null;

        return ['by_category' => $byCategory, 'spent' => $spent, 'budget' => $budget,
            'remaining' => $budget !== null ? Money::amount(Money::sub($budget, $spent)) : null,
            'used_pct' => $budget !== null && (float) $budget > 0 ? round((float) $spent / (float) $budget * 100, 1) : null,
            'over' => $budget !== null && Money::isPositive(Money::sub($spent, $budget))];
    }
}
