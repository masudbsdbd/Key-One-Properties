<?php

namespace App\Modules\Sales\Services;

use App\Models\ActiveFlatClaim;
use App\Models\Flat;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The single live claim on a flat (booking or sale). The unique key on flat_id decides races;
 * callers run inside a transaction with the flat row locked.
 */
class FlatClaims
{
    public function acquire(Flat $flat, Model $claimant, User $user): ActiveFlatClaim
    {
        $existing = ActiveFlatClaim::query()->where('flat_id', $flat->id)->lockForUpdate()->first();
        if ($existing) {
            if ($existing->claimant_type === $claimant->getMorphClass() && (int) $existing->claimant_id === $claimant->getKey()) {
                return $existing;
            }
            throw new ConflictHttpException("Unit {$flat->flat_no} is already booked or sold.");
        }
        if (! in_array($flat->status, ['available'], true)) {
            throw new ConflictHttpException("Unit {$flat->flat_no} is not available.");
        }

        try {
            return DB::transaction(fn () => ActiveFlatClaim::query()->create([
                'flat_id' => $flat->id,
                'claimant_type' => $claimant->getMorphClass(),
                'claimant_id' => $claimant->getKey(),
                'claimed_by' => $user->id,
                'claimed_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException("Unit {$flat->flat_no} was just booked by someone else.");
        }
    }

    /** Hands the claim from a booking to its sale without releasing the flat. */
    public function transfer(Flat $flat, Model $from, Model $to): void
    {
        $updated = ActiveFlatClaim::query()->where('flat_id', $flat->id)
            ->where('claimant_type', $from->getMorphClass())->where('claimant_id', $from->getKey())
            ->update(['claimant_type' => $to->getMorphClass(), 'claimant_id' => $to->getKey(), 'updated_at' => now()]);
        if ($updated !== 1) {
            throw new ConflictHttpException("Unit {$flat->flat_no} is not held by this booking.");
        }
    }

    public function release(Flat $flat, Model $claimant): void
    {
        ActiveFlatClaim::query()->where('flat_id', $flat->id)
            ->where('claimant_type', $claimant->getMorphClass())->where('claimant_id', $claimant->getKey())->delete();
    }

    public function holder(Flat $flat): ?Model
    {
        return ActiveFlatClaim::query()->where('flat_id', $flat->id)->first()?->claimant;
    }
}
