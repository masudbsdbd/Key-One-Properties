<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVisibleProject;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InvestorFlatAgreement extends Model
{
    use BelongsToVisibleProject, HasAttachments, HasPublicId, LogsActivity;

    public const STATUSES = ['draft' => 'Draft', 'pending' => 'Pending Approval', 'active' => 'Active', 'closed' => 'Closed', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected'];

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('investor-agreements')->logOnly(['committed_capital', 'profit_share_percent', 'effective_from', 'effective_to', 'status', 'status_reason'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return ['committed_capital' => 'decimal:2', 'profit_share_percent' => 'decimal:4', 'effective_from' => 'date', 'effective_to' => 'date', 'approved_at' => 'datetime', 'closed_at' => 'datetime'];
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

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** Every version of this investor's agreement on this flat, newest first. */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'flat_id', 'flat_id')->where('investor_id', $this->investor_id)->orderByDesc('version')->orderByDesc('id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    public function coversDate(\DateTimeInterface $date): bool
    {
        return $this->effective_from->lte($date) && ($this->effective_to === null || $this->effective_to->gte($date));
    }
}
