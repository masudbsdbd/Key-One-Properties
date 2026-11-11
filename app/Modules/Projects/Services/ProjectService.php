<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectProgressUpdate;
use App\Models\ProjectStage;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Construction\Services\ConstructionService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $creator): Project
    {
        return DB::transaction(function () use ($data, $creator) {
            $project = new Project(Arr::only($data, (new Project)->getFillable()));
            $project->code = $data['code'] ?? null ?: Project::nextCode();
            $project->created_by = $creator->id;
            $project->save();

            foreach (Project::defaultStages() as $i => $name) {
                $project->stages()->create(['name' => $name, 'sequence' => $i + 1, 'status' => 'not_started']);
            }
            if (! empty($data['default_work_packages'])) {
                app(ConstructionService::class)->addDefaultWorkPackages($project, $creator);
            }
            Warehouse::openSiteStore($project);

            // The creator is always a member so they keep access without projects.view_all.
            $project->members()->attach($creator->id, ['membership_role' => 'Owner', 'is_primary' => true]);

            return $project;
        });
    }

    /**
     * Record a progress report for a stage and roll the stage status forward.
     *
     * @param  array{stage: ProjectStage, progress_percent: float|string, summary: string, report_date: string, visibility: string}  $data
     */
    public function recordProgress(Project $project, array $data, User $user): ProjectProgressUpdate
    {
        return DB::transaction(function () use ($project, $data, $user) {
            /** @var ProjectStage $stage */
            $stage = ProjectStage::query()->whereBelongsTo($project)->lockForUpdate()->findOrFail($data['stage']->id);
            $percent = round((float) $data['progress_percent'], 2);

            $update = $project->progressUpdates()->create([
                'stage_id' => $stage->id,
                'progress_percent' => $percent,
                'previous_percent' => $stage->progress_percent,
                'summary' => $data['summary'],
                'report_date' => $data['report_date'],
                'visibility' => $data['visibility'],
                'recorded_by' => $user->id,
            ]);

            $stage->progress_percent = $percent;
            if ($percent >= 100) {
                $stage->status = 'completed';
                $stage->actual_end_at ??= $data['report_date'];
            } elseif ($percent > 0 && in_array($stage->status, ['not_started', 'completed'], true)) {
                $stage->status = 'in_progress';
                $stage->actual_end_at = null;
            }
            if ($percent > 0) {
                $stage->actual_start_at ??= $data['report_date'];
            }
            $stage->save();

            activity('projects')->event('progress_recorded')->causedBy($user)->performedOn($stage)
                ->tap(fn ($a) => $a->project_id = $project->id)
                ->withProperties([
                    'old' => ['progress' => (float) $update->previous_percent],
                    'attributes' => ['progress' => $percent],
                ])->log('progress_recorded');

            return $update;
        });
    }

    /**
     * Persist a new stage order. Every stage of the project must be listed exactly once.
     *
     * @param  array<int, string>  $publicIds
     */
    public function reorderStages(Project $project, array $publicIds): void
    {
        DB::transaction(function () use ($project, $publicIds) {
            $stages = ProjectStage::query()->whereBelongsTo($project)->lockForUpdate()->get()->keyBy('public_id');

            if (count($publicIds) !== $stages->count() || array_diff($publicIds, $stages->keys()->all())) {
                throw ValidationException::withMessages(['order' => 'The stage list has changed. Reload and try again.']);
            }

            // Two passes avoid colliding with the (project_id, sequence) unique key.
            $offset = 1000;
            foreach ($stages as $stage) {
                $stage->timestamps = false;
                $stage->sequence += $offset;
                $stage->saveQuietly();
            }
            foreach (array_values($publicIds) as $i => $publicId) {
                $stage = $stages[$publicId];
                $stage->sequence = $i + 1;
                $stage->saveQuietly();
            }

            activity('projects')->event('stages_reordered')->causedBy(auth()->user())->performedOn($project)
                ->tap(fn ($a) => $a->project_id = $project->id)
                ->withProperties(['attributes' => ['order' => collect($publicIds)->map(fn ($id) => $stages[$id]->name)->all()]])
                ->log('stages_reordered');
        });
    }
}
