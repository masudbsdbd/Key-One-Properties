<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
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

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class);
    }

    /** A short line about what the cost was for, from its source document. */
    public function sourceDetail(array $labourTypes = []): ?string
    {
        $source = $this->source;

        return match (true) {
            $source instanceof Expense => $source->description,
            $source instanceof LabourEntry => ($labourTypes[$source->labour_type] ?? Str::headline($source->labour_type)).' × '.$source->headcount,
            $source instanceof StockIssue => $source->purpose ?: $source->lines->map(fn ($l) => $l->material?->name)->filter()->unique()
                ->pipe(fn ($names) => $names->take(2)->implode(', ').($names->count() > 2 ? ' +'.($names->count() - 2).' more' : '')),
            $source instanceof ContractorBill => $source->contractor?->name,
            default => null,
        };
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** Document number of the source row, or a readable fallback. */
    public function sourceLabel(): string
    {
        $source = $this->source;
        foreach (['expense_no', 'bill_no', 'issue_no', 'entry_no', 'payment_no'] as $field) {
            if ($source && ! empty($source->{$field})) {
                return $source->{$field};
            }
        }

        return Str::headline(class_basename($this->source_type)).' #'.$this->source_id;
    }
}
