<?php

namespace App\Modules\Collections\Services;

use App\Models\Customer;
use App\Models\CustomerStatementEntry;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Operational customer statement: charges (contract value, late fees, refunds paid out) and
 * credits (payments, waivers). Rows are immutable; one row per source and entry type.
 */
class CustomerStatement
{
    public function post(Customer $customer, string $type, Model $source, CarbonInterface $date, string $description, string $charge, string $credit, ?int $saleId = null, ?int $bookingId = null): CustomerStatementEntry
    {
        return CustomerStatementEntry::query()->firstOrCreate(
            ['source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(), 'entry_type' => $type],
            [
                'customer_id' => $customer->id, 'sale_id' => $saleId, 'booking_id' => $bookingId,
                'entry_date' => $date->toDateString(), 'description' => mb_substr($description, 0, 255),
                'charge_amount' => Money::amount($charge), 'credit_amount' => Money::amount($credit), 'posted_at' => now(),
            ],
        );
    }

    public function reverse(Model $source, string $type, string $reason): ?CustomerStatementEntry
    {
        $original = CustomerStatementEntry::query()->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())->where('entry_type', $type)->first();
        if (! $original) {
            return null;
        }

        return CustomerStatementEntry::query()->firstOrCreate(
            ['source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(), 'entry_type' => 'reversal'],
            [
                'customer_id' => $original->customer_id, 'sale_id' => $original->sale_id, 'booking_id' => $original->booking_id,
                'entry_date' => now(config('app.display_timezone'))->toDateString(),
                'description' => mb_substr('Reversal of '.$original->description.': '.$reason, 0, 255),
                'charge_amount' => $original->credit_amount, 'credit_amount' => $original->charge_amount,
                'posted_at' => now(), 'reversal_of_id' => $original->id,
            ],
        );
    }

    /** Positive = the customer owes; negative = the customer has paid ahead. */
    public static function balance(Customer $customer, ?int $saleId = null): string
    {
        $row = CustomerStatementEntry::query()->where('customer_id', $customer->id)->when($saleId, fn ($q) => $q->where('sale_id', $saleId))
            ->selectRaw('COALESCE(SUM(charge_amount - credit_amount),0) AS b')->toBase()->first();

        return Money::amount((string) $row->b);
    }
}
