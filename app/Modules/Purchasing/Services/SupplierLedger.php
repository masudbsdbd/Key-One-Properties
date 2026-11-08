<?php

namespace App\Modules\Purchasing\Services;

use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class SupplierLedger
{
    public function post(Supplier $supplier, string $type, Model $source, CarbonInterface $date, string $description, string $charge, string $payment, ?User $user): SupplierLedgerEntry
    {
        return SupplierLedgerEntry::query()->firstOrCreate(
            ['source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(), 'entry_type' => $type],
            [
                'supplier_id' => $supplier->id,
                'entry_date' => $date->toDateString(),
                'description' => mb_substr($description, 0, 255),
                'charge_amount' => Money::amount($charge),
                'payment_amount' => Money::amount($payment),
                'posted_by' => $user?->id,
                'posted_at' => now(),
            ],
        );
    }

    public function reverse(Model $source, string $type, string $reason, ?User $user): ?SupplierLedgerEntry
    {
        $original = SupplierLedgerEntry::query()->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())->where('entry_type', $type)->first();
        if (! $original) {
            return null;
        }

        return SupplierLedgerEntry::query()->firstOrCreate(
            ['source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(), 'entry_type' => 'reversal'],
            [
                'supplier_id' => $original->supplier_id,
                'entry_date' => now(config('app.display_timezone'))->toDateString(),
                'description' => mb_substr('Reversal of '.$original->description.': '.$reason, 0, 255),
                // A reversal swaps the sides so the running balance returns to where it was.
                'charge_amount' => $original->payment_amount,
                'payment_amount' => $original->charge_amount,
                'reversal_of_id' => $original->id,
                'posted_by' => $user?->id,
                'posted_at' => now(),
            ],
        );
    }
}
