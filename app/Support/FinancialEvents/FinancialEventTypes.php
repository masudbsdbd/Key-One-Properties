<?php

namespace App\Support\FinancialEvents;

/**
 * Event types the later phases will emit. Phase 1 defines the contract only;
 * no Phase 1 action creates a financial event.
 */
class FinancialEventTypes
{
    public const TYPES = [
        // Phase 2: construction and operations
        'purchase.received' => 'Materials received against a purchase',
        'purchase.supplier_payment' => 'Payment made to a supplier',
        'material.transferred' => 'Material moved between projects (cost moves too)',
        'material.borrowed' => 'Material borrowed by another project',
        'material.returned' => 'Borrowed material returned',
        'contractor.bill_approved' => 'Contractor bill approved',
        'contractor.payment' => 'Payment made to a contractor',
        'expense.general' => 'General (company) expense',
        'expense.project' => 'Project expense',
        'cost.shared_allocated' => 'Shared construction cost allocated to flats by saleable area',

        // Phase 3: sales and investment
        'booking.created' => 'Flat booked by a customer',
        'booking.cancelled' => 'Booking cancelled',
        'sale.confirmed' => 'Flat sale confirmed',
        'installment.due' => 'Installment became due',
        'collection.received' => 'Customer payment received',
        'collection.refunded' => 'Customer payment refunded',
        'investment.received' => 'Investor capital received',
        'investment.withdrawn' => 'Investor capital withdrawn',
        'profit.distribution_approved' => 'Investor profit distribution approved',
        'profit.distribution_paid' => 'Investor profit paid',
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_keys(self::TYPES);
    }
}
