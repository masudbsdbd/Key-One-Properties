<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Immutable project cost row. Corrections are negative rows linked by reversal_of_id.
 */
class ProjectCostAllocation extends Model
{
    public const CATEGORIES = [
        'material' => 'Material',
        'labour' => 'Labour',
        'contractor' => 'Contractor',
        'expense' => 'Other Expense',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'effective_at' => 'date', 'posted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Cost allocations are immutable.'));
        static::deleting(fn () => throw new LogicException('Cost allocations are immutable.'));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
