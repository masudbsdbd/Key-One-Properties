<?php

namespace App\Models\Concerns;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records that carry project_id and follow project visibility.
 */
trait BelongsToVisibleProject
{
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn($this->qualifyColumn('project_id'), Project::query()->visibleTo($user)->select('projects.id'));
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->project !== null && $this->project->isVisibleTo($user);
    }
}
