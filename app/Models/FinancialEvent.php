<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Integration outbox row describing something that has a financial effect.
 * This is NOT a journal entry; the Phase 5 accounting module will consume these
 * rows and create balanced journal entries from them.
 */
class FinancialEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    /** Fields that describe the business fact and can never change once recorded. */
    private const IMMUTABLE = ['event_uuid', 'idempotency_key', 'source_module', 'source_type', 'source_id', 'event_type', 'project_id', 'flat_id', 'customer_id', 'amount', 'currency', 'effective_at', 'occurred_at', 'payload', 'reversal_of_id'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_at' => 'date',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $event) {
            if ($event->isDirty(self::IMMUTABLE)) {
                throw new LogicException('Financial events are immutable; record a reversing event instead.');
            }
        });
        static::deleting(fn () => throw new LogicException('Financial events cannot be deleted.'));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
