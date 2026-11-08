<?php

namespace App\Support\FinancialEvents;

use App\Models\Customer;
use App\Models\FinancialEvent;
use App\Models\Flat;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Records financial facts into the outbox exactly once.
 *
 * The idempotency key is derived from the source record and event type (plus an optional
 * discriminator such as an installment number), so retries of the same business action
 * return the existing row instead of creating a duplicate.
 */
class FinancialEventRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $module,
        Model $source,
        string $eventType,
        ?string $amount,
        CarbonInterface $effectiveAt,
        array $payload = [],
        ?Project $project = null,
        ?Flat $flat = null,
        ?Customer $customer = null,
        ?User $user = null,
        ?string $discriminator = null,
    ): FinancialEvent {
        if (! in_array($eventType, FinancialEventTypes::all(), true)) {
            throw new InvalidArgumentException("Unknown financial event type [{$eventType}].");
        }
        if ($amount !== null && ! preg_match('/^-?\d{1,16}(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must be a decimal string with at most 2 decimals.');
        }
        if (! $source->exists) {
            throw new InvalidArgumentException('The source record must be saved first.');
        }

        $key = self::idempotencyKey($module, $source, $eventType, $discriminator);

        if ($existing = FinancialEvent::query()->where('idempotency_key', $key)->first()) {
            return $existing;
        }

        try {
            return FinancialEvent::query()->create([
                'event_uuid' => (string) Str::uuid(),
                'idempotency_key' => $key,
                'source_module' => $module,
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'event_type' => $eventType,
                'project_id' => $project?->id,
                'flat_id' => $flat?->id,
                'customer_id' => $customer?->id,
                'amount' => $amount,
                'currency' => config('app.currency', 'BDT'),
                'effective_at' => $effectiveAt->toDateString(),
                'occurred_at' => now(),
                'payload' => $payload,
                'processing_status' => FinancialEvent::STATUS_PENDING,
                'recorded_by' => $user?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request recorded the same event first.
            return FinancialEvent::query()->where('idempotency_key', $key)->firstOrFail();
        }
    }

    public static function idempotencyKey(string $module, Model $source, string $eventType, ?string $discriminator = null): string
    {
        return hash('sha256', implode('|', [$module, $source->getMorphClass(), $source->getKey(), $eventType, $discriminator ?? '']));
    }
}
