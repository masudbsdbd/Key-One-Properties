<?php

namespace App\Modules\Sales\Services;

use App\Models\Flat;
use App\Models\FlatPriceRevision;
use App\Models\User;
use App\Modules\Flats\Services\FlatService;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Asking-price changes are requested and approved; only approval writes flat_price_history.
 * Booking and sale prices are snapshots and never follow later revisions.
 */
class PriceRevisionService
{
    public function __construct(private FlatService $flats) {}

    public function request(Flat $flat, string $newPrice, string $reason, User $user): FlatPriceRevision
    {
        return DB::transaction(function () use ($flat, $newPrice, $reason, $user) {
            $flat = Flat::query()->lockForUpdate()->findOrFail($flat->id);
            if ($flat->priceRevisions()->where('status', 'pending')->exists()) {
                throw new ConflictHttpException('A price change for this unit is already waiting for approval.');
            }
            $new = Money::amount($newPrice);
            if (Money::cmp($new, '0') < 0) {
                throw ValidationException::withMessages(['list_price' => 'Enter a valid price.']);
            }
            if ($flat->list_price !== null && Money::cmp($new, $flat->list_price) === 0) {
                throw ValidationException::withMessages(['list_price' => 'The new price is the same as the current price.']);
            }

            return $flat->priceRevisions()->create([
                'old_price' => $flat->list_price, 'new_price' => $new, 'reason' => $reason, 'status' => 'pending', 'requested_by' => $user->id,
            ]);
        });
    }

    public function approve(FlatPriceRevision $revision, User $user): void
    {
        DB::transaction(function () use ($revision, $user) {
            $revision = FlatPriceRevision::query()->with('flat', 'requester')->lockForUpdate()->findOrFail($revision->id);
            Approval::ensureStatus($revision->status, 'pending', 'This price change was already decided.');
            Approval::ensureNotSelf($user, $revision->requested_by, 'a price change');
            $current = Flat::query()->lockForUpdate()->findOrFail($revision->flat_id)->list_price;
            if (Money::cmp((string) ($current ?? '0'), (string) ($revision->old_price ?? '0')) !== 0) {
                throw new ConflictHttpException('The price changed after this request. Request it again.');
            }

            $this->flats->changePrice($revision->flat, $revision->new_price, $revision->reason, $revision->requester, $user);
            $revision->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public function reject(FlatPriceRevision $revision, string $reason, User $user): void
    {
        DB::transaction(function () use ($revision, $reason, $user) {
            $revision = FlatPriceRevision::query()->lockForUpdate()->findOrFail($revision->id);
            Approval::ensureStatus($revision->status, 'pending', 'This price change was already decided.');
            $revision->forceFill(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }
}
