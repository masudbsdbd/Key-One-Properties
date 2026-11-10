<?php

namespace App\Modules\Construction\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ConstructionProgressUpdate;
use App\Models\LabourEntry;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Models\ProjectStage;
use App\Models\User;
use App\Models\WorkPackage;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WorkPackageController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        Gate::authorize('construction:view', $project);

        $spent = Gate::allows('construction:cost-view', $project)
            ? ProjectCostAllocation::query()->where('project_id', $project->id)->whereNotNull('work_package_id')
                ->groupBy('work_package_id')->selectRaw('work_package_id, SUM(amount) AS spent')->pluck('spent', 'work_package_id')
            : null;

        return Ajax::success(null, ['html' => view('construction.work-packages._list', [
            'project' => $project,
            'packages' => $project->workPackages()->with(['stage:id,name', 'responsible:id,name', 'parent:id,code'])
                ->reorder()->orderByRaw('planned_start_at IS NULL')->orderBy('planned_start_at')->orderBy('code')->get(),
            'spent' => $spent,
        ])->render()]);
    }

    public function create(Project $project): JsonResponse
    {
        Gate::authorize('construction:manage', $project);

        return $this->form($project, new WorkPackage(['status' => 'not_started', 'budget_amount' => 0]), 'New Work Package');
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('construction:manage', $project);
        $data = $this->validated($request, $project);

        $package = new WorkPackage($data);
        $package->project_id = $project->id;
        $package->created_by = $request->user()->id;
        $this->applyLinks($package, $request, $project);
        $package->save();

        return Ajax::success('Work package created.');
    }

    public function edit(Project $project, WorkPackage $workPackage): JsonResponse
    {
        Gate::authorize('construction:manage', $project);

        return $this->form($project, $workPackage, 'Edit Work Package');
    }

    public function update(Request $request, Project $project, WorkPackage $workPackage): JsonResponse
    {
        Gate::authorize('construction:manage', $project);
        $workPackage->fill($this->validated($request, $project, $workPackage));
        $this->applyLinks($workPackage, $request, $project);
        $workPackage->save();

        return Ajax::success('Work package updated.');
    }

    public function destroy(Project $project, WorkPackage $workPackage): JsonResponse
    {
        Gate::authorize('construction:manage', $project);

        $used = ConstructionProgressUpdate::query()->where('work_package_id', $workPackage->id)->exists()
            || LabourEntry::query()->where('work_package_id', $workPackage->id)->exists();
        if ($used) {
            return Ajax::error('This work package has progress or labour records.', 409);
        }

        $workPackage->delete();

        return Ajax::success('Work package deleted.');
    }

    public function options(Request $request): JsonResponse
    {
        $project = Project::query()->visibleTo($request->user())->where('public_id', (string) $request->input('project'))->first();
        if (! $project || ! $request->user()->canAny(['construction.view', 'contractors.workorder.create'])) {
            return Ajax::options([]);
        }

        [$results, $more] = TableQuery::options($project->workPackages()->getQuery(), $request, ['code', 'name'],
            fn (WorkPackage $p) => ['id' => $p->public_id, 'text' => $p->label()]);

        return Ajax::options($results, $more);
    }

    private function validated(Request $request, Project $project, ?WorkPackage $package = null): array
    {
        $request->merge([
            'code' => mb_strtoupper(trim((string) $request->input('code'))),
            'budget_amount' => str_replace(',', '', (string) ($request->input('budget_amount') ?: '0')),
        ]);

        return $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-\.]+$/', Rule::unique('construction_work_packages')->where('project_id', $project->id)->whereNull('deleted_at')->ignore($package?->id)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'budget_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'planned_start_at' => ['nullable', 'date'],
            'planned_end_at' => ['nullable', 'date', 'after_or_equal:planned_start_at'],
            'status' => ['required', Rule::in(array_keys(WorkPackage::STATUSES))],
            'stage' => ['nullable', 'string', Rule::exists('project_stages', 'public_id')->where('project_id', $project->id)],
            'parent' => ['nullable', 'string', Rule::exists('construction_work_packages', 'public_id')->where('project_id', $project->id)],
            'responsible' => ['nullable', 'string', Rule::exists('users', 'public_id')],
        ], ['code.regex' => 'Use letters, numbers, dots and dashes only.']);
    }

    private function applyLinks(WorkPackage $package, Request $request, Project $project): void
    {
        $package->stage_id = ProjectStage::query()->whereBelongsTo($project)->where('public_id', $request->input('stage'))->value('id');
        $parentId = WorkPackage::query()->whereBelongsTo($project)->where('public_id', $request->input('parent'))->value('id');
        $package->parent_id = $parentId && $parentId !== $package->id ? $parentId : null;
        $package->responsible_user_id = User::query()->where('public_id', $request->input('responsible'))->value('id');
    }

    private function form(Project $project, WorkPackage $package, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('construction.work-packages._form', [
            'project' => $project,
            'package' => $package->loadMissing(['stage', 'parent', 'responsible']),
            'stages' => $project->stages()->pluck('name', 'public_id'),
            'parents' => $project->workPackages()->when($package->exists, fn ($q) => $q->whereKeyNot($package->id))->get()->mapWithKeys(fn ($p) => [$p->public_id => $p->label()]),
        ])->render()]);
    }
}
