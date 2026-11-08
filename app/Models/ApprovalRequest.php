<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Foundation for module approval workflows. No module uses it in Phase 1.
 */
class ApprovalRequest extends Model
{
    use HasPublicId;

    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected $fillable = ['type', 'requester_id', 'approver_id', 'status', 'request_payload', 'reason', 'decision_note', 'decided_at'];

    protected function casts(): array
    {
        return ['request_payload' => 'array', 'decided_at' => 'datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
