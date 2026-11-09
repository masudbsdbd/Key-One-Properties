<?php

namespace App\Modules\Portal\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Modules\Portal\Services\InvestorPortal;
use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function __invoke(PortalContext $context): View
    {
        $portal = new InvestorPortal($context);
        $summary = Cache::remember(PortalAccess::cacheKey($context->user, 'investor.summary'), 300, fn () => $portal->summary());
        $projects = $context->allows('investor.portal.project.progress.view')
            ? Project::query()->whereIn('id', PortalAccess::investorProjectIds($context->investor))->with('stages')->orderBy('name')->get()
            : collect();

        return view('portal.investor.overview', [
            'context' => $context,
            'summary' => $summary,
            'projects' => $projects,
            'recent' => $portal->capitalTransactions()->take(5),
            'payouts' => $portal->payouts()->take(5),
            'investments' => $portal->agreements()->where('status', 'active')->with(['flat:id,public_id,flat_no,building_id', 'flat.building:id,code', 'project:id,name,code'])->latest('effective_from')->take(6)->get(),
        ]);
    }
}
