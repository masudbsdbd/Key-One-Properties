<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\LogsProjectActivity;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Project extends Model
{
    use HasFactory, HasPublicId, LogsActivity, LogsProjectActivity, SoftDeletes;

    public const STATUSES = [
        'planned' => 'Planned',
        'active' => 'Active',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
    ];

    public const STATUS_TONES = ['planned' => 'info', 'active' => 'success', 'on_hold' => 'warning', 'completed' => 'violet'];

    /** Stages every new project starts with, as kept in Settings (falls back to DEFAULT_STAGES). */
    public static function defaultStages(): array
    {
        $saved = json_decode((string) app(Settings::class)->get('projects.default_stages'), true);

        return is_array($saved) ? array_values(array_filter($saved, 'is_string')) : self::DEFAULT_STAGES;
    }

    public const DEFAULT_STAGES = [
        'Planning & Approvals',
        'Piling & Foundation',
        'Structure',
        'Brickwork & Plaster',
        'Electrical & Plumbing',
        'Finishing',
        'Handover',
    ];

    protected $fillable = [
        'code', 'name', 'description', 'location_text', 'address', 'latitude', 'longitude',
        'start_date', 'target_completion_date', 'actual_completion_date', 'estimated_budget', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'target_completion_date' => 'date',
            'actual_completion_date' => 'date',
            'estimated_budget' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('projects')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected function activityProjectId(): ?int
    {
        return $this->id;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['membership_role', 'is_primary'])->withTimestamps();
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ProjectStage::class)->orderBy('sequence');
    }

    public function progressUpdates(): HasMany
    {
        return $this->hasMany(ProjectProgressUpdate::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProjectMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(ProjectMedia::class, 'cover_media_id');
    }

    public function workPackages(): HasMany
    {
        return $this->hasMany(WorkPackage::class)->orderBy('code');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('planned_at')->orderBy('sort_order');
    }

    public function constructionUpdates(): HasMany
    {
        return $this->hasMany(ConstructionProgressUpdate::class);
    }

    /** Scoped route binding name for {update}. */
    public function updates(): HasMany
    {
        return $this->constructionUpdates();
    }

    public function labourEntries(): HasMany
    {
        return $this->hasMany(LabourEntry::class);
    }

    public function costAllocations(): HasMany
    {
        return $this->hasMany(ProjectCostAllocation::class);
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class)->orderBy('code');
    }

    public function flats(): HasMany
    {
        return $this->hasMany(Flat::class);
    }

    /**
     * Projects a user may see: all with projects.view_all, otherwise only those they are a member of.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('projects.view_all')) {
            return $query;
        }

        if ($user->can('projects.view')) {
            return $query->whereHas('members', fn (Builder $q) => $q->whereKey($user->id));
        }

        return $query->whereRaw('1 = 0');
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->can('projects.view_all')) {
            return true;
        }

        return $user->can('projects.view') && $this->members()->whereKey($user->id)->exists();
    }

    /**
     * Overall progress = simple average of stage progress (every stage weighs the same).
     */
    public function overallProgress(): float
    {
        $average = $this->relationLoaded('stages')
            ? $this->stages->avg('progress_percent')
            : $this->stages()->avg('progress_percent');

        return round((float) $average, 1);
    }

    public static function nextCode(): string
    {
        $next = (int) static::withTrashed()->count() + 1;

        do {
            $code = 'PRJ-'.str_pad((string) $next++, 4, '0', STR_PAD_LEFT);
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
