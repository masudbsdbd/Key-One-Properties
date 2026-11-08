<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVisibleProject;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestorFlatAgreement extends Model
{
    use BelongsToVisibleProject, HasAttachments, HasPublicId;

    public const STATUSES = ['draft' => 'Draft', 'pending' => 'Pending Approval', 'active' => 'Active', 'closed' => 'Closed', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['committed_capital' => 'decimal:2', 'profit_share_percent' => 'decimal:4', 'effective_from' => 'date', 'effective_to' => 'date', 'approved_at' => 'datetime'];
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function capitalTransactions(): HasMany
    {
        return $this->hasMany(InvestorCapitalTransaction::class, 'agreement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
