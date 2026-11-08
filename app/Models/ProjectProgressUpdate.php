<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Immutable progress report. Corrections are made by recording a new update.
 */
class ProjectProgressUpdate extends Model
{
    use HasPublicId;

    public const VISIBILITIES = ['internal' => 'Internal', 'investor' => 'Investor visible'];

    protected $fillable = ['stage_id', 'progress_percent', 'previous_percent', 'summary', 'report_date', 'visibility', 'recorded_by'];

    protected function casts(): array
    {
        return ['report_date' => 'date', 'progress_percent' => 'decimal:2', 'previous_percent' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Progress updates are immutable.'));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class, 'stage_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
