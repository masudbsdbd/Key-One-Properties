<?php

namespace App\Modules\Flats\Services;

use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\InvestorFlatAgreement;
use App\Models\User;
use App\Modules\Portal\Services\CustomerPortal;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Everything that happened on a unit's current deal: buyer, milestones, schedule, money in and out, the
 * running customer ledger and the investors holding the unit. Each part is null when the user may not see it.
 */
class FlatDeal
{
    public static function for(Flat $flat, User $user): ?array
    {
        $canSales = $user->can('sales.sale.view');
        $canBookings = $user->can('sales.booking.view');
        $investors = $user->can('investor.view')
            ? InvestorFlatAgreement::query()->where('flat_id', $flat->id)->whereIn('status', ['active', 'closed'])
                ->with('investor:id,public_id,name,investor_code')->orderByDesc('status')->latest('id')->get()
            : null;
        if (! $canSales && ! $canBookings && ! $investors?->isNotEmpty()) {
            return null;
        }

        $sales = $canSales ? FlatSale::query()->where('flat_id', $flat->id)->with(['customer', 'booking', 'agreement', 'registration', 'handover'])->latest('id')->get() : collect();
        $bookings = $canBookings ? FlatBooking::query()->where('flat_id', $flat->id)->with('customer')->latest('id')->get() : collect();
        $sale = $sales->first(fn ($s) => ! in_array($s->status, ['cancelled', 'rejected'], true));
        $booking = $sale?->booking ?? $bookings->first(fn ($b) => ! in_array($b->status, ['cancelled', 'rejected', 'converted_to_sale'], true));
        $unit = $sale ?? $booking;

        $live = $sale ? in_array($sale->status, FlatSale::ACTIVE, true) : $booking?->status === 'confirmed';
        $canMoney = $unit && $user->can('collections.payment.view');
        $portal = $unit ? new CustomerPortal($unit->customer) : null;
        $position = $live && $canMoney ? $portal->position($unit) : null;
        $plan = $sale && $user->can('collections.plan.view')
            ? InstallmentPlan::query()->where('sale_id', $sale->id)->where('status', 'active')->with('items')->first() : null;

        return [
            'unit' => $unit,
            'sale' => $sale,
            'booking' => $booking,
            'customer' => $unit?->customer,
            'position' => $position,
            'progress' => $position && Money::isPositive($position['contract'])
                ? min(100, round((float) $position['paid'] / (float) $position['contract'] * 100, 1)) : null,
            'steps' => $unit ? self::steps($sale, $booking) : [],
            'plan' => $plan,
            'items' => $plan?->items->sortBy('sequence_no')->values(),
            'payments' => $canMoney ? self::payments($unit) : null,
            'ledger' => $canMoney ? self::ledger($portal, $unit) : null,
            'refunds' => $sale && $user->can('sales.refund.view') ? CustomerRefund::query()->where('sale_id', $sale->id)->latest('id')->get() : null,
            'investors' => $investors,
            'history' => $sales->concat($bookings)->reject(fn ($r) => $r->is($sale) || $r->is($booking))->sortByDesc('created_at')->values(),
            'can_book' => $flat->status === 'available' && $user->can('sales.booking.create'),
        ];
    }

    /** Booking → sale → agreement → registration → handover, with the date each step was reached. */
    private static function steps(?FlatSale $sale, ?FlatBooking $booking): array
    {
        $steps = [
            ['Booked', $booking?->booking_date ?? $booking?->approved_at],
            ['Sale Approved', $sale?->approved_at],
            ['Agreement Signed', $sale?->agreement?->status === 'signed' ? ($sale->agreement->signed_at ?? true) : null],
            ['Registered', $sale?->registration?->status === 'registered' ? ($sale->registration->registered_at ?? true) : null],
            ['Handed Over', $sale?->handover?->status === 'completed' ? ($sale->handover->completed_at ?? true) : null],
        ];
        $current = collect($steps)->search(fn ($s) => ! $s[1]);

        return collect($steps)->map(fn ($s, $i) => [
            'label' => $s[0],
            'date' => $s[1] === true ? null : $s[1],
            'state' => $s[1] ? 'done' : ($i === $current ? 'current' : 'todo'),
        ])->all();
    }

    private static function payments(FlatSale|FlatBooking $unit): Collection
    {
        $query = CustomerPayment::query()->where('customer_id', $unit->customer_id)->whereIn('status', ['pending', 'posted', 'reversed'])->with('receipt');

        return ($unit instanceof FlatSale
            ? $query->where(fn ($q) => $q->where('sale_id', $unit->id)->when($unit->booking_id, fn ($w) => $w->orWhere(fn ($b) => $b->where('booking_id', $unit->booking_id)->whereNull('sale_id'))))
            : $query->where('booking_id', $unit->id)->whereNull('sale_id'))
            ->orderByDesc('payment_date')->orderByDesc('id')->get();
    }

    /** Statement entries oldest first with a running balance (charges minus credits). */
    private static function ledger(CustomerPortal $portal, FlatSale|FlatBooking $unit): Collection
    {
        $balance = '0';

        return $portal->ledger($unit)->orderBy('entry_date')->orderBy('id')->get()->map(function ($e) use (&$balance) {
            $balance = Money::add($balance, Money::sub((string) $e->charge_amount, (string) $e->credit_amount));
            $e->running_balance = Money::amount($balance);

            return $e;
        });
    }
}
