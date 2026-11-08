<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Contracts\Activity;

/**
 * Stamps activity entries with the owning project so a project's history can be listed.
 */
trait LogsProjectActivity
{
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->project_id = $this->activityProjectId();
    }

    protected function activityProjectId(): ?int
    {
        return $this->project_id ?? null;
    }
}
