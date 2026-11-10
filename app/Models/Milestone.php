<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\LogsProjectActivity;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Milestone extends Model
{
    use HasPublicId, LogsActivity, LogsProjectActivity;

    protected $table = 'construction_milestones';

    public const STATUSES = ['pending' => 'Pending', 'completed' => 'Completed'];

    protected $fillable = ['title', 'planned_at', 'completed_at', 'status', 'sort_order'];

    protected function casts(): array
    {
        return ['planned_at' => 'date', 'original_planned_at' => 'date', 'completed_at' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Milestone $m) => $m->original_planned_at ??= $m->planned_at);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(MilestoneChange::class)->orderBy('id');
    }

    /** Days the current plan sits after the first plan; negative when it was brought forward. */
    public function slip(): int
    {
        return $this->original_planned_at ? (int) self::day($this->original_planned_at)->diffInDays(self::day($this->planned_at), false) : 0;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('construction')->logOnly(['title', 'planned_at', 'completed_at', 'status'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class, 'work_package_id');
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'completed' && self::day($this->planned_at)->lt(self::today());
    }

    /**
     * How the milestone stands against its plan: days left or late while pending, early or late once done.
     *
     * @return array{label: string, tone: string}
     */
    public function timing(): array
    {
        $plan = self::day($this->planned_at);
        $days = fn (int $n) => abs($n).' '.Str::plural('day', abs($n));
        if ($this->status === 'completed' && $this->completed_at) {
            $diff = (int) $plan->diffInDays(self::day($this->completed_at), false);

            return match (true) {
                $diff > 0 => ['label' => 'Done '.$days($diff).' late', 'tone' => 'warning'],
                $diff < 0 => ['label' => 'Done '.$days($diff).' early', 'tone' => 'success'],
                default => ['label' => 'Done on time', 'tone' => 'success'],
            };
        }
        $left = (int) self::today()->diffInDays($plan, false);

        return match (true) {
            $left < 0 => ['label' => $days($left).' late', 'tone' => 'danger'],
            $left === 0 => ['label' => 'Due today', 'tone' => 'warning'],
            default => ['label' => $days($left).' left', 'tone' => $left <= 7 ? 'warning' : 'neutral'],
        };
    }

    private static function today(): CarbonImmutable
    {
        return Dates::today();
    }

    private static function day(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString(), config('app.timezone'));
    }
}
