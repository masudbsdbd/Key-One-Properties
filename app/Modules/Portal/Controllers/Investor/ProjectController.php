<?php

namespace App\Modules\Portal\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\ConstructionProgressUpdate;
use App\Models\FlatCostAllocation;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Modules\Investors\Services\CostAllocationService;
use App\Modules\Portal\Services\InvestorPortal;
use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalContext;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Projects the investor holds a unit in. Progress shows only approved, investor-published updates.
 * Project-wide cost needs an explicit grant on the project, per cost category.
 */
class ProjectController extends Controller
{
    public function index(PortalContext $context): View
    {
        abort_unless($context->allows('investor.portal.project.progress.view'), 403);
        $projects = Project::query()->whereIn('id', PortalAccess::investorProjectIds($context->investor))->with('stages')->orderBy('name')->get();

        return view('portal.investor.projects.index', ['projects' => $projects, 'context' => $context]);
    }

    public function show(PortalContext $context, string $project): View
    {
        abort_unless($context->allows('investor.portal.project.progress.view'), 403);
        $record = (new InvestorPortal($context))->project($project);
        $updates = ConstructionProgressUpdate::query()->where('project_id', $record->id)->investorVisible()
            ->with(['workPackage:id,name', 'milestone:id,title', 'attachments'])->latest('published_at')->limit(30)->get();

        return view('portal.investor.projects.show', [
            'project' => $record->load('stages'),
            'milestones' => $record->milestones()->get(),
            'updates' => $updates,
            'canFinancials' => $context->allows('investor.portal.project.financials.view', $record),
            'units' => (new InvestorPortal($context))->agreements()->where('project_id', $record->id)->with('flat:id,public_id,flat_no')->get(),
        ]);
    }

    public function financials(PortalContext $context, string $project): View
    {
        $record = (new InvestorPortal($context))->project($project);
        abort_unless($context->allows('investor.portal.project.financials.view', $record), 403);
        $categories = (new InvestorPortal($context))->visibleCostCategories($record);
        $run = CostAllocationService::latestApproved($record->id);
        $allocated = $run ? FlatCostAllocation::query()->where('cost_run_id', $run->id)->whereIn('cost_category', array_keys($categories))
            ->selectRaw('cost_category, allocation_type, SUM(allocated_amount) AS total')->groupBy('cost_category', 'allocation_type')->get() : collect();
        $posted = ProjectCostAllocation::query()->where('project_id', $record->id)->whereIn('cost_category', array_keys($categories))
            ->selectRaw('cost_category, SUM(amount) AS total')->groupBy('cost_category')->pluck('total', 'cost_category');

        $rows = collect($categories)->map(fn ($label, $key) => [
            'label' => $label,
            'shared' => Money::amount((string) ($allocated->where('cost_category', $key)->firstWhere('allocation_type', 'shared')->total ?? 0)),
            'direct' => Money::amount((string) ($allocated->where('cost_category', $key)->firstWhere('allocation_type', 'direct')->total ?? 0)),
            'posted' => Money::amount((string) ($posted[$key] ?? 0)),
        ]);

        return view('portal.investor.projects.financials', ['project' => $record, 'rows' => $rows, 'run' => $run]);
    }

    /** Image of an investor-published update of a project the investor holds a unit in. */
    public function photo(Request $request, PortalContext $context, string $file): Response
    {
        abort_unless($context->allows('investor.portal.project.progress.view'), 403);
        $updates = ConstructionProgressUpdate::query()->investorVisible()->whereIn('project_id', PortalAccess::investorProjectIds($context->investor))->select('id');
        $attachment = Attachment::query()->where('public_id', $file)->where('attachable_type', (new ConstructionProgressUpdate)->getMorphClass())
            ->whereIn('attachable_id', $updates)->first();
        abort_unless($attachment && $attachment->isImage(), 404);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime_type, 'Cache-Control' => 'private, max-age=600']);
    }
}
