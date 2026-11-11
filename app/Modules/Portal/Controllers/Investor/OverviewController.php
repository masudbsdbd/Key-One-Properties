<?php

namespace App\Modules\Portal\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Modules\Investors\Controllers\CapitalController;
use App\Modules\Investors\Services\InvestorLedger;
use App\Modules\Portal\Services\InvestorPortal;
use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalContext;
use Illuminate\Support\Collection;
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

        $investments = $portal->agreements()->where('status', 'active')
            ->with(['flat:id,public_id,flat_no,building_id', 'flat.building:id,code,name', 'project:id,public_id,name,code'])->latest('effective_from')->take(6)->get();

        return view('portal.investor.overview', [
            'context' => $context,
            'summary' => $summary,
            'progress' => $projects->mapWithKeys(fn (Project $p) => [$p->id => $p->overallProgress()]),
            'investments' => $investments,
            'positions' => $investments->mapWithKeys(fn ($a) => [$a->id => InvestorLedger::balances($context->investor, $a->flat_id)]),
            'movements' => $this->movements($portal, $context->allows('investor.portal.profit.view'), $context->allows('investor.portal.documents.download')),
        ]);
    }

    /**
     * Money in and out in the investor's own words, newest first.
     *
     * @return Collection<int, array{date: mixed, title: string, detail: string, amount: string, state: ?string, tone: string, document: ?string}>
     */
    private function movements(InvestorPortal $portal, bool $profit, bool $documents): Collection
    {
        $items = $portal->capitalTransactions()->map(fn ($t) => [
            'date' => $t->transaction_date,
            'title' => $t->transaction_type === 'contribution' ? 'You Invested' : 'Capital Returned to You',
            'detail' => $t->flat ? 'Unit '.$t->flat->flat_no : '',
            'amount' => (string) $t->amount,
            'state' => ['pending' => 'Awaiting Confirmation', 'reversed' => 'Cancelled'][$t->status] ?? null,
            'tone' => $t->transaction_type === 'contribution' ? 'info' : 'accent',
            'document' => $documents && $t->status !== 'pending' ? route('investor.documents.capital', $t->public_id) : null,
        ]);
        if ($profit) {
            $items = $items->concat($portal->payouts()->map(fn ($p) => [
                'date' => $p->paid_at,
                'title' => 'Profit Paid to You',
                'detail' => CapitalController::METHODS[$p->payment_method] ?? '',
                'amount' => (string) $p->amount,
                'state' => $p->status === 'reversed' ? 'Cancelled' : null,
                'tone' => 'success',
                'document' => $documents ? route('investor.documents.payout', $p->public_id) : null,
            ]));
        }

        return $items->sortByDesc('date')->take(8)->values();
    }
}
