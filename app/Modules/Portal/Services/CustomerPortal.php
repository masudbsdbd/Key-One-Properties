<?php

namespace App\Modules\Portal\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\CustomerStatementEntry;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\InstallmentReschedule;
use App\Models\InstallmentScheduleItem;
use App\Models\LateFeeAssessment;
use App\Modules\Collections\Services\PaymentService;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only view of one customer's units, schedules, payments and statement. Figures come from the
 * Phase 3 statement, allocations and plans; nothing is stored or recomputed for the portal.
 */
class CustomerPortal
{
    public function __construct(private Customer $customer) {}

    public static function for(PortalContext $context): self
    {
        return new self($context->customer);
    }

    public function customer(): Customer
    {
        return $this->customer;
    }

    /** Approved sales (current and cancelled) of this customer. */
    public function sales(bool $activeOnly = false): Builder
    {
        return FlatSale::query()->where('customer_id', $this->customer->id)
            ->whereIn('status', $activeOnly ? FlatSale::ACTIVE : [...FlatSale::ACTIVE, 'cancelled'])
            ->with(['flat.building', 'flat.floor', 'project', 'registration', 'handover']);
    }

    /** Confirmed bookings not yet turned into a sale. */
    public function bookings(): Builder
    {
        return FlatBooking::query()->where('customer_id', $this->customer->id)->where('status', 'confirmed')
            ->whereDoesntHave('sale', fn ($q) => $q->whereIn('status', FlatSale::ACTIVE))->with(['flat.building', 'flat.floor', 'project']);
    }

    /** A sale or booking of this customer by its public id, or 404. */
    public function unit(string $publicId): FlatSale|FlatBooking
    {
        return $this->sales()->where('public_id', $publicId)->first() ?? $this->bookings()->where('public_id', $publicId)->first() ?? abort(404);
    }

    /** Statement entries of a sale, including the money paid against the booking it came from. */
    public function ledger(FlatSale|FlatBooking $unit): Builder
    {
        $query = CustomerStatementEntry::query()->where('customer_id', $this->customer->id);

        return $unit instanceof FlatSale
            ? $query->where(fn ($q) => $q->where('sale_id', $unit->id)->when($unit->booking_id, fn ($w) => $w->orWhere(fn ($b) => $b->where('booking_id', $unit->booking_id)->whereNull('sale_id'))))
            : $query->where('booking_id', $unit->id)->whereNull('sale_id');
    }

    /**
     * @return array{contract: string, paid: string, late_fees: string, refunded: string, balance: string, advance: string, overdue: string, next: ?InstallmentScheduleItem, plan: ?InstallmentPlan}
     */
    public function position(FlatSale|FlatBooking $unit): array
    {
        $rows = $this->ledger($unit)->selectRaw('entry_type, SUM(charge_amount) AS charge, SUM(credit_amount) AS credit')->groupBy('entry_type')->get()->keyBy('entry_type');
        $sum = fn ($type, $col) => (string) ($rows[$type]->{$col} ?? 0);
        $balance = Money::amount(Money::add('0', ...$rows->map(fn ($r) => Money::sub((string) $r->charge, (string) $r->credit))->values()->all()));
        $paidQuery = CustomerPayment::query()->where('customer_id', $this->customer->id)->where('status', 'posted');
        $paid = $unit instanceof FlatSale
            ? $paidQuery->where(fn ($q) => $q->where('sale_id', $unit->id)->when($unit->booking_id, fn ($w) => $w->orWhere(fn ($b) => $b->where('booking_id', $unit->booking_id)->whereNull('sale_id'))))->sum('amount')
            : $paidQuery->where('booking_id', $unit->id)->whereNull('sale_id')->sum('amount');
        $plan = $unit instanceof FlatSale ? InstallmentPlan::query()->where('sale_id', $unit->id)->where('status', 'active')->with('items')->first() : null;
        $open = $plan ? $plan->items->where('status', '!=', 'rescheduled')->filter(fn ($i) => $i->isOpen())->sortBy('due_date') : collect();
        $today = Dates::today();

        return [
            'contract' => Money::amount($unit instanceof FlatSale ? (string) $unit->net_contract_value : (string) $unit->agreed_price),
            'paid' => Money::amount((string) $paid),
            'late_fees' => Money::amount(Money::sub($sum('late_fee', 'charge'), $sum('fee_waiver', 'credit'))),
            'refunded' => Money::amount($sum('refund', 'charge')),
            'balance' => $balance,
            'advance' => $unit instanceof FlatSale ? PaymentService::unappliedAdvance($unit) : '0.00',
            'overdue' => Money::amount(Money::add('0', ...$open->filter(fn ($i) => $i->due_date->lt($today))->map(fn ($i) => $i->outstanding())->values()->all())),
            'next' => $open->first(fn ($i) => $i->due_date->gte($today)) ?? $open->first(),
            'plan' => $plan,
        ];
    }

    public function reschedules(FlatSale $sale): Collection
    {
        return InstallmentReschedule::query()->whereIn('plan_id', InstallmentPlan::query()->where('sale_id', $sale->id)->select('id'))->where('status', 'applied')->latest('id')->get();
    }

    public function lateFees(FlatSale $sale): Collection
    {
        return LateFeeAssessment::query()->whereHas('item.plan', fn ($q) => $q->where('sale_id', $sale->id))->with('item:id,sequence_no,due_date')->latest('id')->get();
    }

    /** Posted and reversed payments; pending or rejected ones are not shown. */
    public function payments(): Builder
    {
        return CustomerPayment::query()->where('customer_id', $this->customer->id)->whereIn('status', ['posted', 'reversed'])
            ->with(['sale:id,public_id,sale_no,flat_id', 'sale.flat:id,flat_no', 'booking:id,public_id,booking_no,flat_id', 'booking.flat:id,flat_no', 'receipt', 'allocations.item:id,sequence_no,due_date']);
    }

    public function payment(string $publicId): CustomerPayment
    {
        return $this->payments()->where('public_id', $publicId)->first() ?? abort(404);
    }

    public function refunds(): Collection
    {
        return CustomerRefund::query()->where('customer_id', $this->customer->id)->whereIn('status', ['paid', 'reversed'])->latest('paid_on')->get();
    }

    public function statement(): Builder
    {
        return CustomerStatementEntry::query()->where('customer_id', $this->customer->id);
    }
}
