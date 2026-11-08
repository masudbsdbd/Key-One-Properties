<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialTransfer extends Model
{
    use HasPublicId;

    public const STATUSES = ['submitted' => 'Submitted', 'approved' => 'Approved', 'posted' => 'Posted', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'reversed' => 'Reversed'];

    public const KINDS = ['direct' => 'Direct Transfer', 'conversion' => 'Borrow Conversion'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'posted_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialTransferLine::class, 'transfer_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function sourceProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'source_project_id');
    }

    public function destinationProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'destination_project_id');
    }

    public function borrow(): BelongsTo
    {
        return $this->belongsTo(MaterialBorrow::class, 'borrow_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->sourceWarehouse->isVisibleTo($user) || $this->destinationWarehouse->isVisibleTo($user);
    }
}
