<?php

namespace App\Modules\Flats\Services;

use App\Models\Building;
use App\Models\Flat;
use App\Models\Floor;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FlatService
{
    public const BULK_LIMIT = 500;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Floor $floor, array $attributes, ?string $price, User $user): Flat
    {
        return DB::transaction(function () use ($floor, $attributes, $price, $user) {
            $flat = $this->make($floor, $attributes, $user);
            $flat->save();

            $this->recordInitialHistory($flat, $price, $user);

            return $flat;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Flat $flat, Floor $floor, array $attributes): Flat
    {
        if ($floor->building_id !== $flat->building_id) {
            throw ValidationException::withMessages(['floor' => 'A unit can only move to another floor of the same building.']);
        }

        $flat->fill($attributes);
        $flat->floor_id = $floor->id;
        $flat->save();

        return $flat;
    }

    public function changePrice(Flat $flat, string $newPrice, string $reason, User $user, ?User $approver = null): void
    {
        DB::transaction(function () use ($flat, $newPrice, $reason, $user, $approver) {
            $locked = Flat::query()->lockForUpdate()->findOrFail($flat->id);
            $new = number_format((float) $newPrice, 2, '.', '');

            if ($locked->list_price !== null && number_format((float) $locked->list_price, 2, '.', '') === $new) {
                throw ValidationException::withMessages(['list_price' => 'The new price is the same as the current price.']);
            }

            $locked->priceHistory()->create([
                'old_price' => $locked->list_price,
                'new_price' => $new,
                'reason' => $reason,
                'changed_by' => $user->id,
                'approved_by' => $approver?->id,
                'effective_at' => now(),
            ]);

            $locked->list_price = $new;
            $locked->save();
        });
    }

    /** Booked / sold / available transitions driven by the sales workflow. Caller holds the flat lock. */
    public function setSalesStatus(Flat $flat, string $status, string $reason, User $user): void
    {
        if ($flat->status === $status) {
            return;
        }
        $flat->statusHistory()->create(['from_status' => $flat->status, 'to_status' => $status, 'reason' => $reason, 'changed_by' => $user->id, 'changed_at' => now()]);
        $flat->status = $status;
        $flat->save();
    }

    public function changeStatus(Flat $flat, string $status, ?string $reason, User $user): void
    {
        DB::transaction(function () use ($flat, $status, $reason, $user) {
            $locked = Flat::query()->lockForUpdate()->findOrFail($flat->id);

            if ($locked->isLockedBySales()) {
                throw new ConflictHttpException('This unit is managed by the sales workflow.');
            }
            if (! in_array($status, Flat::MANUAL_STATUSES, true)) {
                throw ValidationException::withMessages(['status' => 'This status cannot be set manually.']);
            }
            if ($locked->status === $status) {
                throw ValidationException::withMessages(['status' => 'The unit already has this status.']);
            }
            if ($status === 'available' && ! Money::isPositive((string) $locked->list_price)) {
                throw ValidationException::withMessages(['status' => 'Set a list price before releasing this unit for sale.']);
            }

            $locked->statusHistory()->create([
                'from_status' => $locked->status,
                'to_status' => $status,
                'reason' => $reason,
                'changed_by' => $user->id,
                'changed_at' => now(),
            ]);

            $locked->status = $status;
            $locked->save();
        });
    }

    /**
     * Sets one manual status on many units. Units held by a booking or sale, units without a list price
     * (when releasing) and units already in that status are skipped and counted.
     *
     * @param  iterable<Flat>  $flats
     * @return array{changed: int, sales: int, no_price: int, same: int}
     */
    public function bulkChangeStatus(iterable $flats, string $status, ?string $reason, User $user): array
    {
        if (! in_array($status, Flat::MANUAL_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'This status cannot be set manually.']);
        }
        $result = ['changed' => 0, 'sales' => 0, 'no_price' => 0, 'same' => 0];
        DB::transaction(function () use ($flats, $status, $reason, $user, &$result) {
            foreach ($flats as $flat) {
                $locked = Flat::query()->lockForUpdate()->find($flat->id);
                $skip = match (true) {
                    ! $locked || $locked->isLockedBySales() || in_array($locked->status, ['booked', 'sold'], true) => 'sales',
                    $locked->status === $status => 'same',
                    $status === 'available' && ! Money::isPositive((string) $locked->list_price) => 'no_price',
                    default => null,
                };
                if ($skip) {
                    $result[$skip]++;

                    continue;
                }
                $locked->statusHistory()->create(['from_status' => $locked->status, 'to_status' => $status,
                    'reason' => $reason ?: ($status === 'available' ? 'Released for sale' : 'Bulk status change'), 'changed_by' => $user->id, 'changed_at' => now()]);
                $locked->status = $status;
                $locked->save();
                $result['changed']++;
            }
        });

        return $result;
    }

    /**
     * Build the list of units a bulk request would create.
     *
     * @param  Collection<int, Floor>  $floors
     * @return Collection<int, array{floor: Floor, flat_no: string, exists: bool}>
     */
    public function bulkPlan(Building $building, Collection $floors, int $perFloor, string $pattern, int $startIndex = 1): Collection
    {
        $plan = collect();
        foreach ($floors as $floor) {
            for ($i = 0; $i < $perFloor; $i++) {
                $plan->push(['floor' => $floor, 'flat_no' => self::flatNumber($pattern, $floor, $startIndex + $i)]);
            }
        }

        if ($plan->count() > self::BULK_LIMIT) {
            throw ValidationException::withMessages(['per_floor' => 'A batch can create at most '.self::BULK_LIMIT.' units.']);
        }

        $existing = Flat::withTrashed()->where('building_id', $building->id)
            ->whereIn('flat_no', $plan->pluck('flat_no'))->pluck('flat_no')->map(fn ($n) => mb_strtoupper($n))->all();
        $counts = $plan->countBy(fn ($row) => mb_strtoupper($row['flat_no']));

        return $plan->map(fn ($row) => $row + [
            'exists' => in_array(mb_strtoupper($row['flat_no']), $existing, true),
            'duplicate' => $counts[mb_strtoupper($row['flat_no'])] > 1,
        ]);
    }

    /**
     * @param  Collection<int, array{floor: Floor, flat_no: string, exists: bool, duplicate: bool}>  $plan
     * @param  array<string, mixed>  $attributes
     */
    public function bulkCreate(Collection $plan, array $attributes, ?string $price, User $user): int
    {
        if ($plan->contains(fn ($row) => $row['exists'] || $row['duplicate'])) {
            throw ValidationException::withMessages(['pattern' => 'Some unit numbers already exist or repeat. Change the pattern.']);
        }

        return DB::transaction(function () use ($plan, $attributes, $price, $user) {
            foreach ($plan as $row) {
                $flat = $this->make($row['floor'], $attributes + ['flat_no' => $row['flat_no']], $user);
                $flat->save();
                $this->recordInitialHistory($flat, $price, $user, 'Bulk created');
            }

            return $plan->count();
        });
    }

    /**
     * Pattern tokens: {floor} floor number, {n} sequence number, {L} sequence letter (A, B, C...).
     */
    public static function flatNumber(string $pattern, Floor $floor, int $index): string
    {
        $floorToken = $floor->floor_number < 0 ? 'B'.abs($floor->floor_number) : ($floor->floor_number === 0 ? 'G' : (string) $floor->floor_number);
        $letter = '';
        for ($n = $index; $n > 0; $n = intdiv($n - 1, 26)) {
            $letter = chr(65 + (($n - 1) % 26)).$letter;
        }

        return mb_strtoupper(strtr($pattern, ['{floor}' => $floorToken, '{n}' => (string) $index, '{L}' => $letter]));
    }

    private function make(Floor $floor, array $attributes, User $user): Flat
    {
        $building = $floor->building;

        $flat = new Flat(Arr::only($attributes, (new Flat)->getFillable()));
        $flat->flat_no = mb_strtoupper(trim($attributes['flat_no']));
        $flat->project_id = $building->project_id;
        $flat->building_id = $building->id;
        $flat->floor_id = $floor->id;
        $flat->status = 'not_released';
        $flat->created_by = $user->id;

        return $flat;
    }

    private function recordInitialHistory(Flat $flat, ?string $price, User $user, string $reason = 'Initial price'): void
    {
        $flat->statusHistory()->create([
            'from_status' => null,
            'to_status' => $flat->status,
            'reason' => 'Created',
            'changed_by' => $user->id,
            'changed_at' => now(),
        ]);

        if ($price !== null && $price !== '') {
            $flat->priceHistory()->create([
                'old_price' => null,
                'new_price' => $price,
                'reason' => $reason,
                'changed_by' => $user->id,
                'effective_at' => now(),
            ]);
            $flat->forceFill(['list_price' => $price])->saveQuietly();
        }
    }
}
