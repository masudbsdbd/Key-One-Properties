<?php

namespace App\Modules\Collections\Services;

use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\InstallmentReschedule;
use App\Models\InstallmentScheduleItem;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Plan = contract value − booking money already received. Down payment is the first item; the rest is
 * split evenly (remainder on the last item) or entered line by line. Paid history is never rewritten.
 */
class InstallmentService
{
    public function __construct(private PaymentService $payments) {}

    /** Booking money received before the sale (posted payments less refunds requested or paid). */
    public static function bookingCredit(FlatSale $sale): string
    {
        if (! $sale->booking_id) {
            return '0.00';
        }
        $paid = (string) CustomerPayment::query()->where('booking_id', $sale->booking_id)->where('purpose', 'booking')->where('status', 'posted')->sum('amount');
        $refunds = (string) CustomerRefund::query()->where('booking_id', $sale->booking_id)->whereNull('sale_id')->whereIn('status', ['pending', 'approved', 'paid'])->sum('amount');

        return Money::amount(Money::sub($paid, $refunds));
    }

    /**
     * @param  array{start_date: string, down_payment_amount?: string, installment_frequency: string, count?: int, first_due_date?: string, lines?: array<int, array{due_date: string, amount: string}>}  $data
     * @return array{contract_value: string, booking_credit: string, principal: string, down_payment: string, items: array<int, array{kind: string, due_date: string, amount: string}>}
     */
    public function build(FlatSale $sale, array $data): array
    {
        $contract = Money::amount($sale->net_contract_value);
        $credit = self::bookingCredit($sale);
        $principal = Money::amount(Money::sub($contract, $credit));
        $down = Money::amount($data['down_payment_amount'] ?? '0');
        if (Money::cmp($down, '0') < 0 || Money::cmp($down, $principal) > 0) {
            throw ValidationException::withMessages(['down_payment_amount' => 'Down payment must be between 0 and '.$principal.'.']);
        }
        $rest = Money::sub($principal, $down);
        $items = [];
        if (Money::isPositive($down)) {
            $items[] = ['kind' => 'down_payment', 'due_date' => $data['start_date'], 'amount' => $down];
        }

        if ($data['installment_frequency'] === 'custom') {
            $lines = array_values(array_filter($data['lines'] ?? [], fn ($l) => ($l['amount'] ?? '') !== '' && ($l['due_date'] ?? '') !== ''));
            $sum = Money::add('0', ...array_map(fn ($l) => (string) $l['amount'], $lines));
            if (Money::cmp($sum, $rest) !== 0) {
                throw ValidationException::withMessages(['lines' => 'Installments add up to '.Money::amount($sum).'; they must equal '.Money::amount($rest).'.']);
            }
            usort($lines, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']));
            foreach ($lines as $l) {
                $items[] = ['kind' => 'installment', 'due_date' => $l['due_date'], 'amount' => Money::amount($l['amount'])];
            }
        } elseif (Money::isPositive($rest)) {
            $count = max(1, (int) ($data['count'] ?? 1));
            $step = $data['installment_frequency'] === 'quarterly' ? 3 : 1;
            $each = Money::amount(Money::div($rest, (string) $count, 2));
            $first = Carbon::parse($data['first_due_date'] ?? $data['start_date']);
            for ($i = 0; $i < $count; $i++) {
                $amount = $i === $count - 1 ? Money::amount(Money::sub($rest, Money::mul($each, (string) ($count - 1)))) : $each;
                $items[] = ['kind' => 'installment', 'due_date' => $first->copy()->addMonthsNoOverflow($i * $step)->toDateString(), 'amount' => $amount];
            }
        }

        return ['contract_value' => $contract, 'booking_credit' => $credit, 'principal' => $principal, 'down_payment' => $down, 'items' => $items];
    }

    public function create(FlatSale $sale, array $data, User $user): InstallmentPlan
    {
        return DB::transaction(function () use ($sale, $data, $user) {
            $sale = FlatSale::query()->lockForUpdate()->findOrFail($sale->id);
            Approval::ensureStatus($sale->status, array_diff(FlatSale::ACTIVE, ['cancel_requested']), 'Plans need an approved sale.');
            if (InstallmentPlan::query()->where('sale_id', $sale->id)->whereIn('status', ['draft', 'pending', 'active'])->exists()) {
                throw new ConflictHttpException('This sale already has an installment plan.');
            }
            $built = $this->build($sale, $data);

            $plan = InstallmentPlan::query()->create([
                'plan_no' => DocumentNumber::next('PL'), 'sale_id' => $sale->id, 'customer_id' => $sale->customer_id,
                'contract_value' => $built['contract_value'], 'booking_credit' => $built['booking_credit'], 'principal_amount' => $built['principal'],
                'down_payment_amount' => $built['down_payment'], 'installment_frequency' => $data['installment_frequency'], 'start_date' => $data['start_date'],
                'status' => 'draft', 'created_by' => $user->id,
            ]);
            foreach ($built['items'] as $i => $item) {
                $plan->items()->create(['sequence_no' => $i + 1, 'kind' => $item['kind'], 'due_date' => $item['due_date'], 'principal_due' => $item['amount'], 'status' => 'upcoming']);
            }

            return $plan;
        });
    }

    public function submit(InstallmentPlan $plan, User $user): void
    {
        DB::transaction(function () use ($plan) {
            $plan = InstallmentPlan::query()->lockForUpdate()->findOrFail($plan->id);
            Approval::ensureStatus($plan->status, ['draft', 'rejected'], 'This plan was already submitted.');
            $plan->forceFill(['status' => 'pending'])->save();
        });
    }

    /** Activates the plan and applies any advance the customer already paid against the sale. */
    public function approve(InstallmentPlan $plan, User $user): void
    {
        DB::transaction(function () use ($plan, $user) {
            $plan = InstallmentPlan::query()->lockForUpdate()->findOrFail($plan->id);
            Approval::ensureStatus($plan->status, 'pending', 'Only plans waiting for approval can be approved.');
            Approval::ensureNotSelf($user, $plan->created_by, 'an installment plan');
            $sale = FlatSale::query()->findOrFail($plan->sale_id);
            if (Money::cmp(Money::add($plan->principal_amount, $plan->booking_credit), $sale->net_contract_value) !== 0
                || Money::cmp(self::bookingCredit($sale), $plan->booking_credit) !== 0) {
                throw new ConflictHttpException('The sale or booking money changed after this plan was drafted. Create the plan again.');
            }
            $plan->forceFill(['status' => 'active', 'approved_by' => $user->id, 'approved_at' => now()])->save();
            $this->payments->applyAdvance($plan);
        });
    }

    public function reject(InstallmentPlan $plan, User $user): void
    {
        DB::transaction(function () use ($plan) {
            $plan = InstallmentPlan::query()->lockForUpdate()->findOrFail($plan->id);
            Approval::ensureStatus($plan->status, 'pending', 'Only plans waiting for approval can be rejected.');
            $plan->forceFill(['status' => 'rejected'])->save();
        });
    }

    public function discard(InstallmentPlan $plan, User $user): void
    {
        DB::transaction(function () use ($plan) {
            $plan = InstallmentPlan::query()->lockForUpdate()->findOrFail($plan->id);
            Approval::ensureStatus($plan->status, ['draft', 'rejected'], 'Only draft plans can be discarded.');
            $plan->forceFill(['status' => 'cancelled'])->save();
        });
    }

    // -------------------------------------------------------------- reschedule

    /**
     * Unpaid principal of open items is moved to new lines. Items with unpaid late fees stay as they are.
     *
     * @param  array<int, array{due_date: string, amount: string}>  $lines
     */
    public function requestReschedule(InstallmentPlan $plan, array $lines, string $reason, User $user): InstallmentReschedule
    {
        return DB::transaction(function () use ($plan, $lines, $reason, $user) {
            $plan = InstallmentPlan::query()->lockForUpdate()->findOrFail($plan->id);
            Approval::ensureStatus($plan->status, 'active', 'Only active plans can be rescheduled.');
            if ($plan->reschedules()->where('status', 'requested')->exists()) {
                throw new ConflictHttpException('A reschedule is already waiting for approval.');
            }
            [$old, $total] = $this->openForReschedule($plan);
            if (! $old) {
                throw ValidationException::withMessages(['lines' => 'Nothing is left to reschedule.']);
            }
            $lines = array_values(array_filter($lines, fn ($l) => ($l['amount'] ?? '') !== '' && ($l['due_date'] ?? '') !== ''));
            $sum = Money::add('0', ...array_map(fn ($l) => (string) $l['amount'], $lines));
            if (Money::cmp($sum, $total) !== 0) {
                throw ValidationException::withMessages(['lines' => 'New installments add up to '.Money::amount($sum).'; they must equal '.$total.'.']);
            }
            usort($lines, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']));

            return $plan->reschedules()->create([
                'reschedule_no' => DocumentNumber::next('RS'), 'reason' => $reason, 'status' => 'requested', 'requested_by' => $user->id,
                'old_schedule_snapshot' => $old,
                'new_schedule_snapshot' => array_map(fn ($l) => ['due_date' => $l['due_date'], 'amount' => Money::amount($l['amount'])], $lines),
            ]);
        });
    }

    public function approveReschedule(InstallmentReschedule $reschedule, User $user): void
    {
        DB::transaction(function () use ($reschedule, $user) {
            $reschedule = InstallmentReschedule::query()->lockForUpdate()->findOrFail($reschedule->id);
            Approval::ensureStatus($reschedule->status, 'requested', 'This reschedule was already decided.');
            Approval::ensureNotSelf($user, $reschedule->requested_by, 'a reschedule');
            $plan = InstallmentPlan::query()->lockForUpdate()->findOrFail($reschedule->plan_id);
            [$old] = $this->openForReschedule($plan);
            if ($old != $reschedule->old_schedule_snapshot) {
                throw new ConflictHttpException('Payments changed the schedule after this request. Request the reschedule again.');
            }

            InstallmentScheduleItem::query()->whereIn('id', array_column($old, 'id'))->update(['status' => 'rescheduled']);
            $next = (int) $plan->items()->max('sequence_no');
            foreach ($reschedule->new_schedule_snapshot as $i => $line) {
                $plan->items()->create(['sequence_no' => $next + $i + 1, 'kind' => 'installment', 'due_date' => $line['due_date'], 'principal_due' => $line['amount'], 'status' => 'upcoming']);
            }
            $plan->items()->get()->each(function (InstallmentScheduleItem $item) {
                $item->refreshStatus();
                $item->save();
            });
            $reschedule->forceFill(['status' => 'applied', 'approved_by' => $user->id, 'applied_at' => now()])->save();
            $plan->increment('version');
        });
    }

    public function rejectReschedule(InstallmentReschedule $reschedule, string $reason, User $user): void
    {
        DB::transaction(function () use ($reschedule, $reason, $user) {
            $reschedule = InstallmentReschedule::query()->lockForUpdate()->findOrFail($reschedule->id);
            Approval::ensureStatus($reschedule->status, 'requested', 'This reschedule was already decided.');
            $reschedule->forceFill(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $user->id])->save();
        });
    }

    /** @return array{0: array<int, array{id: int, sequence_no: int, due_date: string, principal_outstanding: string}>, 1: string} */
    private function openForReschedule(InstallmentPlan $plan): array
    {
        $items = $plan->items()->lockForUpdate()->get()->filter(fn (InstallmentScheduleItem $i) => $i->status !== 'rescheduled'
            && Money::isPositive($i->principalOutstanding()) && ! Money::isPositive($i->feeOutstanding()));
        $old = $items->map(fn ($i) => ['id' => $i->id, 'sequence_no' => $i->sequence_no, 'due_date' => $i->due_date->toDateString(), 'principal_outstanding' => $i->principalOutstanding()])->values()->all();

        return [$old, Money::amount(Money::add('0', ...array_column($old, 'principal_outstanding')))];
    }
}
