<?php

namespace App\Modules\Construction\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\WorkPackage;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MilestoneController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        Gate::authorize('construction:view', $project);

        return Ajax::success(null, ['html' => view('construction.milestones._list', [
            'project' => $project,
            'milestones' => $project->milestones()->with('workPackage:id,code,name')->get(),
        ])->render()]);
    }

    public function create(Project $project): JsonResponse
    {
        Gate::authorize('construction:manage', $project);

        return $this->form($project, new Milestone(['status' => 'pending']), 'New Milestone');
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('construction:manage', $project);
        $milestone = new Milestone($this->validated($request));
        $milestone->project_id = $project->id;
        $milestone->work_package_id = $this->packageId($request, $project);
        $milestone->save();

        return Ajax::success('Milestone added.');
    }

    public function edit(Project $project, Milestone $milestone): JsonResponse
    {
        Gate::authorize('construction:manage', $project);

        return $this->form($project, $milestone, 'Edit Milestone');
    }

    public function update(Request $request, Project $project, Milestone $milestone): JsonResponse
    {
        Gate::authorize('construction:manage', $project);
        $milestone->fill($this->validated($request));
        $milestone->work_package_id = $this->packageId($request, $project);
        $milestone->save();

        return Ajax::success('Milestone updated.');
    }

    public function complete(Project $project, Milestone $milestone): JsonResponse
    {
        Gate::authorize('construction:manage', $project);
        abort_if($milestone->status === 'completed', 409, 'This milestone is already done.');
        $milestone->update(['status' => 'completed', 'completed_at' => now(config('app.display_timezone'))->toDateString()]);

        return Ajax::success($milestone->title.' done.');
    }

    public function destroy(Project $project, Milestone $milestone): JsonResponse
    {
        Gate::authorize('construction:manage', $project);
        $milestone->delete();

        return Ajax::success('Milestone deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'planned_at' => ['required', 'date'],
            'status' => ['required', Rule::in(array_keys(Milestone::STATUSES))],
            'completed_at' => ['nullable', 'date', 'required_if:status,completed'],
            'work_package' => ['nullable', 'string', 'size:16'],
        ]);
        if ($data['status'] !== 'completed') {
            $data['completed_at'] = null;
        }

        return $data;
    }

    private function packageId(Request $request, Project $project): ?int
    {
        return WorkPackage::query()->whereBelongsTo($project)->where('public_id', $request->input('work_package'))->value('id');
    }

    private function form(Project $project, Milestone $milestone, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('construction.milestones._form', [
            'project' => $project,
            'milestone' => $milestone,
            'packages' => $project->workPackages()->get()->mapWithKeys(fn ($p) => [$p->public_id => $p->label()]),
        ])->render()]);
    }
}
