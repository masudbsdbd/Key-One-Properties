<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockIssue extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_at' => 'date', 'issued_at' => 'date', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockIssueLine::class, 'stock_issue_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(StockIssueReturn::class, 'stock_issue_id');
    }

    /** Posted, with something still on site to bring back. */
    public function isReturnable(): bool
    {
        return $this->status === 'posted' && $this->lines->contains(fn (StockIssueLine $l) => Money::isPositive($l->remaining()));
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class, 'work_package_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function number(): string
    {
        return $this->issue_no;
    }
}
