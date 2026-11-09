<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVisibleProject;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InvestorCapitalTransaction extends Model
{
    use BelongsToVisibleProject, HasAttachments, HasPublicId, LogsActivity;

    public const TYPES = ['contribution' => 'Contribution', 'capital_return' => 'Capital Return'];

    public const STATUSES = ['pending' => 'Pending', 'posted' => 'Posted', 'rejected' => 'Rejected', 'reversed' => 'Reversed'];

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('investor-capital')->logOnly(['amount', 'transaction_date', 'status', 'status_reason'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transaction_date' => 'date', 'posted_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(InvestorFlatAgreement::class, 'agreement_id');
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
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
