<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class JournalEntry extends Model
{
    use HasPublicId;

    public const STATUSES = ['draft' => 'Draft', 'submitted' => 'Waiting for Approval', 'approved' => 'Approved', 'posted' => 'Posted', 'rejected' => 'Rejected'];

    public const TYPES = [
        'manual' => 'Journal', 'system' => 'Automatic', 'opening' => 'Opening Balance', 'transfer' => 'Transfer',
        'receipt' => 'Money In', 'payment' => 'Money Out', 'closing' => 'Year Closing', 'reversal' => 'Reversal',
    ];

    /** A posted journal may only be marked as reversed; nothing else about it changes. */
    private const AFTER_POSTING = ['reversed_by_id', 'updated_at'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'accounting_date' => 'date', 'total_amount' => 'decimal:2',
            'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (JournalEntry $entry) {
            if ($entry->getOriginal('status') === 'posted' && array_diff(array_keys($entry->getDirty()), self::AFTER_POSTING) !== []) {
                throw new LogicException('A posted journal cannot be changed.');
            }
        });
        static::deleting(function (JournalEntry $entry) {
            if ($entry->status === 'posted') {
                throw new LogicException('A posted journal cannot be deleted.');
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_no');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FinancialEvent::class, 'financial_event_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isReversed(): bool
    {
        return $this->reversed_by_id !== null;
    }
}
