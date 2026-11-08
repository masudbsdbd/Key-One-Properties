<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialBorrow extends Model
{
    use HasPublicId;

    public const STATUSES = [
        'submitted' => 'Submitted', 'approved' => 'Approved', 'issued' => 'Issued',
        'partially_returned' => 'Partially Returned', 'partially_converted' => 'Partially Converted',
        'returned' => 'Returned', 'converted' => 'Converted', 'closed' => 'Closed',
        'rejected' => 'Rejected', 'cancelled' => 'Cancelled',
    ];

    public const OPEN = ['issued', 'partially_returned', 'partially_converted'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'issued_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialBorrowLine::class, 'borrow_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(MaterialBorrowAction::class, 'borrow_id')->orderBy('id');
    }

    public function sourceProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'source_project_id');
    }

    public function destinationProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'destination_project_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->sourceProject->isVisibleTo($user) || $this->destinationProject->isVisibleTo($user);
    }
}
