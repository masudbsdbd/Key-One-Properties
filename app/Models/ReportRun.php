<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReportRun extends Model
{
    use HasPublicId;

    public const STATUSES = ['queued' => 'Queued', 'running' => 'Running', 'completed' => 'Ready', 'failed' => 'Failed', 'expired' => 'Expired'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['filters_json' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function file(): HasOne
    {
        return $this->hasOne(ReportFile::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'completed' && $this->file !== null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
