<?php

namespace App\Modules\Collections\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\MoneyReceipt;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Customer money: collected (pending) → posted by a second person. Posting allocates to the oldest
 * due items first (late fee, then principal), then to later items; anything left is an unapplied advance.
 */
class PaymentService
{
    public function __construct(private CustomerStatement $statement, private FinancialEventRecorder $events) {}

    public function collect(?FlatBooking $booking, ?FlatSale $sale, array $data, User $user, string $key): CustomerPayment
    {
        return DB::transaction(function () use ($booking, $sale, $data, $user, $key) {
            if ($existing = CustomerPayment::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }
            if ($sale) {
                Approval::ensureStatus($sale->status, FlatSale::ACTIVE, 'Payments are taken against an approved sale.');
            } else {
                Approval::ensureStatus($booking->status, ['confirmed'], 'Booking money is taken against a confirmed booking.');
            }
            if (! Money::isPositive($data['amount'])) {
                throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
            }
            $doc = $sale ?? $booking;

            return CustomerPayment::query()->create([
                'payment_no' => DocumentNumber::next('CP'), 'customer_id' => $doc->customer_id, 'project_id' => $doc->project_id,
                'purpose' => $sale ? 'sale' : 'booking', 'booking_id' => $booking?->id, 'sale_id' => $sale?->id,
                'payment_date' => $data['payment_date'], 'amount' => Money::amount($data['amount']), 'method' => $data['method'],
                'reference_no' => $data['reference_no'] ?? null, 'notes' => $data['notes'] ?? null,
                'status' => 'pending', 'received_by' => $user->id, 'idempotency_key' => $key,
            ]);
        });
    }

    /**
     * What posting would do, without saving.
     *
     * @return array<int, array{item: ?InstallmentScheduleItem, type: string, amount: string}>
     */
    public function preview(FlatSale $sale, string $amount): array
    {
        $plan = $this->activePlan($sale);

        return $this->allocate($plan ? $plan->items()->get() : collect(), $amount);
    }

    public function post(CustomerPayment $payment, User $user): void
    {
        DB::transaction(function () use ($payment, $user) {
            $payment = CustomerPayment::query()->with(['customer', 'project', 'booking.flat', 'sale.flat'])->lockForUpdate()->findOrFail($payment->id);
            Approval::ensureStatus($payment->status, 'pending', 'This payment is no longer pending.');
            Approval::ensureNotSelf($user, $payment->received_by, 'a payment');

            if ($payment->purpose === 'booking') {
                PaymentAllocation::query()->create(['payment_id' => $payment->id, 'allocation_type' => 'booking', 'amount' => $payment->amount, 'created_at' => now()]);
            } else {
                $plan = $this->activePlan($payment->sale);
                $items = $plan ? InstallmentScheduleItem::query()->where('plan_id', $plan->id)->lockForUpdate()->get() : collect();
                $this->write($payment, $this->allocate($items, $payment->amount));
            }

            $payment->forceFill(['status' => 'posted', 'approved_by' => $user->id, 'posted_at' => now()])->save();
            MoneyReceipt::query()->create(['receipt_no' => DocumentNumber::next('MR'), 'payment_id' => $payment->id, 'issued_at' => now(), 'issued_by' => $user->id]);
            $unit = ($payment->sale ?? $payment->booking)->flat->flat_no;
            $this->statement->post($payment->customer, 'payment', $payment, $payment->payment_date,
                'Payment '.$payment->payment_no.' · Unit '.$unit, '0', $payment->amount, $payment->sale_id, $payment->booking_id ?? $payment->sale?->booking_id);
            $this->events->record('collections', $payment, 'collection.received', $payment->amount, $payment->payment_date,
                ['payment_no' => $payment->payment_no, 'purpose' => $payment->purpose, 'method' => $payment->method, 'source_module' => 'collections'],
                project: $payment->project, flat: ($payment->sale ?? $payment->booking)->flat, customer: $payment->customer, user: $user);
        });
    }

    public function reject(CustomerPayment $payment, string $reason, User $user): void
    {
        DB::transaction(function () use ($payment, $reason, $user) {
            $payment = CustomerPayment::query()->lockForUpdate()->findOrFail($payment->id);
            Approval::ensureStatus($payment->status, 'pending', 'This payment is no longer pending.');
            $payment->forceFill(['status' => 'rejected', 'status_reason' => $reason, 'approved_by' => $user->id])->save();
        });
    }

    /** Undoes the allocations with negative rows, voids the receipt and reverses the statement and event. */
    public function reverse(CustomerPayment $payment, string $reason, User $user): void
    {
        DB::transaction(function () use ($payment, $reason, $user) {
            $payment = CustomerPayment::query()->with(['booking.sale'])->lockForUpdate()->findOrFail($payment->id);
            Approval::ensureStatus($payment->status, 'posted', 'Only posted payments can be reversed.');
            if ($payment->purpose === 'booking' && $payment->booking?->sale && InstallmentPlan::query()->where('sale_id', $payment->booking->sale->id)->whereIn('status', ['pending', 'active'])->exists()) {
                throw new ConflictHttpException('This booking money is part of an installment plan. Cancel the plan first.');
            }

            $rows = PaymentAllocation::query()->where('payment_id', $payment->id)->whereNull('reversal_of_id')->get();
            $items = InstallmentScheduleItem::query()->whereIn('id', $rows->pluck('schedule_item_id')->filter())->lockForUpdate()->get()->keyBy('id');
            foreach ($rows as $row) {
                PaymentAllocation::query()->create(['payment_id' => $payment->id, 'schedule_item_id' => $row->schedule_item_id, 'allocation_type' => $row->allocation_type,
                    'amount' => Money::neg($row->amount), 'reversal_of_id' => $row->id, 'created_at' => now()]);
                if ($row->schedule_item_id && ($item = $items[$row->schedule_item_id] ?? null)) {
                    $field = $row->allocation_type === 'late_fee' ? 'fee_paid' : 'principal_paid';
                    $item->{$field} = Money::amount(Money::sub($item->{$field}, $row->amount));
                }
            }
            $items->each(function (InstallmentScheduleItem $item) {
                $item->refreshStatus();
                $item->save();
            });

            $payment->forceFill(['status' => 'reversed', 'reversed_by' => $user->id, 'reversed_at' => now(), 'status_reason' => $reason])->save();
            if ($payment->sale_id && ($plan = InstallmentPlan::query()->where('sale_id', $payment->sale_id)->where('status', 'active')->first())) {
                $this->applyAdvance($plan);
            }
            MoneyReceipt::query()->where('payment_id', $payment->id)->update(['voided_at' => now()]);
            $this->statement->reverse($payment, 'payment', $reason);
            $this->events->reverse('collections', $payment, now(config('app.display_timezone')), $user);
        });
    }

    /** Moves unapplied advances of the sale's posted payments onto open items. */
    public function applyAdvance(InstallmentPlan $plan): string
    {
        $items = InstallmentScheduleItem::query()->where('plan_id', $plan->id)->lockForUpdate()->get();
        $applied = '0';
        $payments = CustomerPayment::query()->where('sale_id', $plan->sale_id)->where('status', 'posted')->orderBy('posted_at')->orderBy('id')->lockForUpdate()->get();
        foreach ($payments as $payment) {
            $advance = Money::amount((string) PaymentAllocation::query()->where('payment_id', $payment->id)->where('allocation_type', 'unapplied_advance')->sum('amount'));
            if (! Money::isPositive($advance)) {
                continue;
            }
            $lines = array_values(array_filter($this->allocate($items, $advance), fn ($l) => $l['type'] !== 'unapplied_advance'));
            $used = Money::add('0', ...array_column($lines, 'amount'));
            if (! Money::isPositive($used)) {
                break;
            }
            PaymentAllocation::query()->create(['payment_id' => $payment->id, 'allocation_type' => 'unapplied_advance', 'amount' => Money::neg($used), 'created_at' => now()]);
            $this->write($payment, $lines);
            $applied = Money::add($applied, $used);
        }

        return Money::amount($applied);
    }

    public static function unappliedAdvance(FlatSale $sale): string
    {
        return Money::amount((string) PaymentAllocation::query()->where('allocation_type', 'unapplied_advance')
            ->whereIn('payment_id', CustomerPayment::query()->where('sale_id', $sale->id)->where('status', 'posted')->select('id'))->sum('amount'));
    }

    public function activePlan(FlatSale $sale): ?InstallmentPlan
    {
        return InstallmentPlan::query()->where('sale_id', $sale->id)->where('status', 'active')->first();
    }

    /**
     * Oldest due date first; within an item the late fee before principal. Mutates the item models.
     *
     * @param  Collection<int, InstallmentScheduleItem>  $items
     * @return array<int, array{item: ?InstallmentScheduleItem, type: string, amount: string}>
     */
    private function allocate(Collection $items, string $amount): array
    {
        $left = Money::amount($amount);
        $lines = [];
        foreach ($items->filter(fn ($i) => $i->isOpen())->sortBy([['due_date', 'asc'], ['sequence_no', 'asc']]) as $item) {
            foreach (['late_fee' => 'feeOutstanding', 'principal' => 'principalOutstanding'] as $type => $method) {
                $due = $item->{$method}();
                if (! Money::isPositive($due) || ! Money::isPositive($left)) {
                    continue;
                }
                $take = Money::cmp($due, $left) <= 0 ? $due : $left;
                $lines[] = ['item' => $item, 'type' => $type, 'amount' => Money::amount($take)];
                $field = $type === 'late_fee' ? 'fee_paid' : 'principal_paid';
                $item->{$field} = Money::amount(Money::add($item->{$field}, $take));
                $left = Money::sub($left, $take);
            }
        }
        if (Money::isPositive($left)) {
            $lines[] = ['item' => null, 'type' => 'unapplied_advance', 'amount' => Money::amount($left)];
        }

        return $lines;
    }

    private function write(CustomerPayment $payment, array $lines): void
    {
        foreach ($lines as $line) {
            PaymentAllocation::query()->create(['payment_id' => $payment->id, 'schedule_item_id' => $line['item']?->id, 'allocation_type' => $line['type'], 'amount' => $line['amount'], 'created_at' => now()]);
        }
        collect($lines)->pluck('item')->filter()->unique('id')->each(function (InstallmentScheduleItem $item) {
            $item->refreshStatus();
            $item->save();
        });
    }
}
