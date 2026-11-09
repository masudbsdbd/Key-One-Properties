<?php

namespace App\Modules\Collections\Services;

use App\Models\Customer;
use App\Models\CustomerLateFeePolicy;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\LateFeeAssessment;
use App\Models\User;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Late fees exist only where a policy is set for the customer (optionally one sale). Percent applies to
 * the unpaid principal of the overdue item on the assessment date. One charge per item, policy and period.
 */
class LateFeeService
{
    public function __construct(private CustomerStatement $statement, private FinancialEventRecorder $events) {}

    public function savePolicy(?CustomerLateFeePolicy $policy, Customer $customer, ?FlatSale $sale, array $data, User $user): CustomerLateFeePolicy
    {
        if ($data['fee_type'] === 'percent' && Money::cmp($data['fee_value'], '100') > 0) {
            throw ValidationException::withMessages(['fee_value' => 'A percentage cannot be above 100.']);
        }
        if ($policy && $policy->assessments()->exists()) {
            throw new ConflictHttpException('Fees were charged under this policy. End it and create a new one.');
        }
        $policy ??= new CustomerLateFeePolicy(['created_by' => $user->id]);
        $policy->fill([
            'customer_id' => $customer->id, 'sale_id' => $sale?->id, 'fee_type' => $data['fee_type'],
            'fee_value' => $data['fee_type'] === 'none' ? '0' : (string) $data['fee_value'], 'grace_days' => (int) ($data['grace_days'] ?? 0),
            'frequency' => $data['frequency'], 'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'] ?? null,
        ]);
        $policy->approved_by = $user->id;
        $policy->save();

        return $policy;
    }

    public static function policyFor(InstallmentPlan $plan, CarbonInterface $on): ?CustomerLateFeePolicy
    {
        return CustomerLateFeePolicy::query()->where('customer_id', $plan->customer_id)
            ->where(fn ($q) => $q->whereNull('sale_id')->orWhere('sale_id', $plan->sale_id))
            ->whereDate('effective_from', '<=', $on)->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on))
            ->orderByRaw('sale_id IS NULL')->orderByDesc('effective_from')->orderByDesc('id')->first();
    }

    /** @return int number of new charges */
    public function assess(InstallmentPlan $plan, CarbonInterface $on, ?User $user = null): int
    {
        return DB::transaction(function () use ($plan, $on, $user) {
            $plan = InstallmentPlan::query()->with(['customer', 'sale.flat', 'sale.project'])->lockForUpdate()->findOrFail($plan->id);
            if ($plan->status !== 'active' || ! ($policy = self::policyFor($plan, $on)) || $policy->fee_type === 'none' || ! Money::isPositive($policy->fee_value)) {
                return 0;
            }
            $count = 0;
            $items = InstallmentScheduleItem::query()->where('plan_id', $plan->id)->lockForUpdate()->get();
            foreach ($items as $item) {
                $principal = $item->principalOutstanding();
                if (! Money::isPositive($principal) || $item->due_date->copy()->addDays($policy->grace_days)->gte($on->copy()->startOfDay())) {
                    continue;
                }
                $period = $policy->frequency === 'per_month' ? $on->format('Y-m') : 'once';
                $key = 'late-fee:'.$item->id.':'.$policy->id.':'.$period;
                if (LateFeeAssessment::query()->where('idempotency_key', $key)->exists()) {
                    continue;
                }
                $amount = $policy->fee_type === 'fixed' ? Money::amount($policy->fee_value) : Money::amount(Money::div(Money::mul($principal, $policy->fee_value, 6), '100', 2));
                if (! Money::isPositive($amount)) {
                    continue;
                }

                $assessment = LateFeeAssessment::query()->create(['schedule_item_id' => $item->id, 'policy_id' => $policy->id, 'assessed_on' => $on->toDateString(),
                    'period' => $period, 'amount' => $amount, 'status' => 'posted', 'assessed_by' => $user?->id, 'idempotency_key' => $key]);
                $item->fee_due = Money::amount(Money::add($item->fee_due, $amount));
                $item->refreshStatus();
                $item->save();
                $this->statement->post($plan->customer, 'late_fee', $assessment, $on, 'Late fee · '.$plan->plan_no.' #'.$item->sequence_no, $amount, '0', $plan->sale_id);
                $this->events->record('collections', $assessment, 'late_fee.assessed', $amount, $on,
                    ['plan_no' => $plan->plan_no, 'installment' => $item->sequence_no, 'period' => $period],
                    project: $plan->sale->project, flat: $plan->sale->flat, customer: $plan->customer, user: $user);
                $count++;
            }

            return $count;
        });
    }

    public function waive(LateFeeAssessment $assessment, string $reason, User $user): void
    {
        DB::transaction(function () use ($assessment, $reason, $user) {
            $assessment = LateFeeAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            Approval::ensureStatus($assessment->status, 'posted', 'This fee was already waived.');
            $item = InstallmentScheduleItem::query()->with('plan.customer', 'plan.sale.project', 'plan.sale.flat')->lockForUpdate()->findOrFail($assessment->schedule_item_id);
            if (Money::cmp($item->feeOutstanding(), $assessment->amount) < 0) {
                throw new ConflictHttpException('Part of this fee was already paid; reverse that payment first.');
            }
            $item->fee_due = Money::amount(Money::sub($item->fee_due, $assessment->amount));
            $item->refreshStatus();
            $item->save();
            $assessment->forceFill(['status' => 'waived', 'waived_by' => $user->id, 'waived_at' => now(), 'reason' => $reason])->save();
            $plan = $item->plan;
            $this->statement->post($plan->customer, 'fee_waiver', $assessment, now(config('app.display_timezone')), 'Late fee waived · '.$plan->plan_no.' #'.$item->sequence_no, '0', $assessment->amount, $plan->sale_id);
            $this->events->record('collections', $assessment, 'late_fee.waived', Money::neg($assessment->amount), now(config('app.display_timezone')),
                ['plan_no' => $plan->plan_no, 'reason' => $reason], project: $plan->sale->project, flat: $plan->sale->flat, customer: $plan->customer, user: $user);
        });
    }

    /** Daily job: refresh overdue statuses and charge fees on every active plan. */
    public function runDaily(CarbonInterface $on): array
    {
        $plans = 0;
        $charges = 0;
        InstallmentPlan::query()->where('status', 'active')->orderBy('id')->each(function (InstallmentPlan $plan) use ($on, &$plans, &$charges) {
            DB::transaction(function () use ($plan, $on) {
                InstallmentScheduleItem::query()->where('plan_id', $plan->id)->lockForUpdate()->get()->each(function (InstallmentScheduleItem $i) use ($on) {
                    $before = $i->status;
                    $i->refreshStatus($on->copy()->startOfDay());
                    if ($i->status !== $before) {
                        $i->save();
                    }
                });
            });
            $charges += $this->assess($plan, $on);
            $plans++;
        });

        return ['plans' => $plans, 'charges' => $charges];
    }
}
