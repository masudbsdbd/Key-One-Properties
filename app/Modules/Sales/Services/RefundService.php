<?php

namespace App\Modules\Sales\Services;

use App\Models\CustomerRefund;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\User;
use App\Modules\Collections\Services\CustomerStatement;
use App\Support\DocumentNumber;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Refund request → approval → payment. Never more than the customer paid for the booking or sale.
 */
class RefundService
{
    public function __construct(private CustomerStatement $statement, private FinancialEventRecorder $events) {}

    public function request(?FlatBooking $booking, ?FlatSale $sale, string $amount, string $reason, User $user, string $key): CustomerRefund
    {
        return DB::transaction(function () use ($booking, $sale, $amount, $reason, $user, $key) {
            if ($existing = CustomerRefund::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }
            $doc = $sale ?? $booking;
            $max = SalesService::refundable($booking, $sale);
            if (! Money::isPositive($amount) || Money::cmp($amount, $max) > 0) {
                throw ValidationException::withMessages(['amount' => 'Refund can be up to '.$max.'.']);
            }

            return CustomerRefund::query()->create([
                'refund_no' => DocumentNumber::next('RF'), 'customer_id' => $doc->customer_id, 'project_id' => $doc->project_id,
                'booking_id' => $booking?->id ?? $sale?->booking_id, 'sale_id' => $sale?->id, 'amount' => Money::amount($amount),
                'reason' => $reason, 'status' => 'pending', 'requested_by' => $user->id, 'idempotency_key' => $key,
            ]);
        });
    }

    public function approve(CustomerRefund $refund, User $user): void
    {
        DB::transaction(function () use ($refund, $user) {
            $refund = CustomerRefund::query()->lockForUpdate()->findOrFail($refund->id);
            Approval::ensureStatus($refund->status, 'pending', 'This refund was already decided.');
            Approval::ensureNotSelf($user, $refund->requested_by, 'a refund');
            $refund->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public function reject(CustomerRefund $refund, string $reason, User $user): void
    {
        DB::transaction(function () use ($refund, $reason, $user) {
            $refund = CustomerRefund::query()->lockForUpdate()->findOrFail($refund->id);
            Approval::ensureStatus($refund->status, 'pending', 'This refund was already decided.');
            $refund->forceFill(['status' => 'rejected', 'status_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public function pay(CustomerRefund $refund, array $data, User $user): void
    {
        DB::transaction(function () use ($refund, $data, $user) {
            $refund = CustomerRefund::query()->with(['customer', 'project', 'sale', 'booking'])->lockForUpdate()->findOrFail($refund->id);
            Approval::ensureStatus($refund->status, 'approved', 'Only approved refunds can be paid.');

            $refund->forceFill([
                'status' => 'paid', 'paid_by' => $user->id, 'paid_at' => now(), 'paid_on' => $data['paid_on'],
                'payment_method' => $data['payment_method'], 'payment_reference' => $data['payment_reference'] ?? null,
            ])->save();
            $this->statement->post($refund->customer, 'refund', $refund, $refund->paid_on, 'Refund '.$refund->refund_no, $refund->amount, '0', $refund->sale_id, $refund->booking_id);
            $this->events->record('sales', $refund, 'collection.refunded', $refund->amount, $refund->paid_on,
                ['refund_no' => $refund->refund_no, 'method' => $refund->payment_method], project: $refund->project, customer: $refund->customer, user: $user);
        });
    }

    public function reverse(CustomerRefund $refund, string $reason, User $user): void
    {
        DB::transaction(function () use ($refund, $reason, $user) {
            $refund = CustomerRefund::query()->lockForUpdate()->findOrFail($refund->id);
            Approval::ensureStatus($refund->status, 'paid', 'Only paid refunds can be reversed.');
            $refund->forceFill(['status' => 'reversed', 'reversed_at' => now(), 'status_reason' => $reason])->save();
            $this->statement->reverse($refund, 'refund', $reason);
            $this->events->reverse('sales', $refund, now(config('app.display_timezone')), $user);
        });
    }
}
