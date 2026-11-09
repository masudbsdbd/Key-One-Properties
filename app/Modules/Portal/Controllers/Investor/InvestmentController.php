<?php

namespace App\Modules\Portal\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\InvestorFlatAgreement;
use App\Models\Project;
use App\Modules\Investors\Services\CostAllocationService;
use App\Modules\Portal\Services\InvestorPortal;
use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalContext;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestmentController extends Controller
{
    public function index(PortalContext $context): View
    {
        abort_unless($context->allows('investor.portal.investments.view'), 403);

        return view('portal.investor.investments.index', [
            'projects' => Project::query()->whereIn('id', PortalAccess::investorProjectIds($context->investor))->orderBy('name')->pluck('name', 'public_id'),
        ]);
    }

    public function table(Request $request, PortalContext $context): JsonResponse
    {
        abort_unless($context->allows('investor.portal.investments.view'), 403);
        $query = (new InvestorPortal($context))->agreements()->with(['flat:id,public_id,flat_no,building_id', 'flat.building:id,code', 'project:id,public_id,name,code']);
        if (in_array($status = (string) $request->input('status'), ['active', 'closed'], true)) {
            $query->where('status', $status);
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }
        $rows = TableQuery::apply($query, $request, ['agreement_no', 'flat.flat_no'], ['from' => 'effective_from', 'capital' => 'committed_capital', 'share' => 'profit_share_percent'], 'from', 'desc');

        return Ajax::table(view('portal.investor.investments._rows', ['agreements' => $rows])->render(), $rows);
    }

    public function show(PortalContext $context, string $agreement): View
    {
        abort_unless($context->allows('investor.portal.investments.view'), 403);
        $portal = new InvestorPortal($context);
        $record = $portal->agreement($agreement);
        $flat = $record->flat;
        $run = CostAllocationService::latestApproved($flat->project_id);

        return view('portal.investor.investments.show', [
            'agreement' => $record,
            'flat' => $flat,
            'versions' => InvestorFlatAgreement::query()->where('investor_id', $context->investor->id)->where('flat_id', $flat->id)->whereIn('status', ['active', 'closed'])->orderByDesc('version')->get(),
            'position' => $portal->flatPosition($flat),
            'capital' => $portal->capitalTransactions($flat),
            'payouts' => $portal->payouts($flat),
            'timeline' => $portal->timeline($record),
            'files' => $context->allows('investor.portal.documents.download') ? $portal->sharedFiles($record) : collect(),
            'categories' => $portal->visibleCostCategories($flat),
            'run' => $run,
            'runCost' => $run ? CostAllocationService::flatCost($run, $flat->id) : null,
            'canFinancials' => $context->allows('investor.portal.flat.financials.view', $flat),
            'canProfit' => $context->allows('investor.portal.profit.view', $flat),
            'canDocuments' => $context->allows('investor.portal.documents.download'),
        ]);
    }
}
