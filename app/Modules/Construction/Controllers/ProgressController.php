<?php

namespace App\Modules\Construction\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ConstructionProgressUpdate;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\WorkPackage;
use App\Modules\Construction\Services\ConstructionService;
use App\Support\Ajax;
use App\Support\Attachments\AttachmentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProgressController extends Controller
{
    public function __construct(private ConstructionService $service) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('construction:view', $project);

        $updates = $project->constructionUpdates()->with(['workPackage:id,code,name', 'milestone:id,title', 'submitter:id,name', 'approver:id,name', 'attachments'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('report_date')->latest('id')->limit(100)->get();

        return Ajax::success(null, ['html' => view('construction.progress._timeline', ['project' => $project, 'updates' => $updates])->render()]);
    }

    public function create(Project $project): JsonResponse
    {
        Gate::authorize('construction:progress-submit', $project);

        return Ajax::success(null, ['title' => 'Submit Progress', 'html' => view('construction.progress._form', [
            'project' => $project,
            'packages' => $project->workPackages()->get()->mapWithKeys(fn ($p) => [$p->public_id => $p->label().' ('.rtrim(rtrim($p->progress_percent, '0'), '.').'%)']),
            'milestones' => $project->milestones()->where('status', 'pending')->pluck('title', 'public_id'),
        ])->render()]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('construction:progress-submit', $project);

        $data = $request->validate([
            'work_package' => ['required', 'string', Rule::exists('construction_work_packages', 'public_id')->where('project_id', $project->id)->whereNull('deleted_at')],
            'milestone' => ['nullable', 'string', Rule::exists('construction_milestones', 'public_id')->where('project_id', $project->id)],
            'progress_percent' => ['required', 'numeric', 'between:0,100'],
            'report_date' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'notes' => ['required', 'string', 'max:5000'],
            'visibility' => ['required', Rule::in(array_keys(ConstructionProgressUpdate::VISIBILITIES))],
            ...AttachmentStore::rules('evidence'),
        ], ['evidence.*.mimes' => 'Allowed: images or PDF.', 'evidence.*.max' => 'Each file must be 10 MB or smaller.']);
        $data['work_package'] = WorkPackage::query()->whereBelongsTo($project)->where('public_id', $data['work_package'])->first();
        $data['milestone'] = Milestone::query()->whereBelongsTo($project)->where('public_id', $data['milestone'] ?? null)->first();

        $update = DB::transaction(function () use ($project, $data, $request) {
            $update = $this->service->submitProgress($project, $data, $request->user());
            foreach ($request->file('evidence', []) as $file) {
                AttachmentStore::store($update, 'construction-progress', $file, $request->user());
            }

            return $update;
        });

        return Ajax::success('Progress submitted.');
    }

    public function approve(Request $request, Project $project, ConstructionProgressUpdate $update): JsonResponse
    {
        Gate::authorize('construction:progress-approve', $project);
        $this->service->approveProgress($update, $request->user());

        return Ajax::success('Progress approved.');
    }

    public function rejectForm(Project $project, ConstructionProgressUpdate $update): JsonResponse
    {
        Gate::authorize('construction:progress-approve', $project);

        return Ajax::success(null, ['title' => 'Reject Progress', 'html' => view('partials.reason-form', [
            'action' => route('construction.progress.reject', [$project, $update]), 'button' => 'Reject',
        ])->render()]);
    }

    public function reject(Request $request, Project $project, ConstructionProgressUpdate $update): JsonResponse
    {
        Gate::authorize('construction:progress-approve', $project);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->service->rejectProgress($update, $data['reason'], $request->user());

        return Ajax::success('Progress rejected.');
    }
}
