<?php

namespace App\Modules\Portal\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\Flat;
use App\Models\InvestorLedgerEntry;
use App\Modules\Investors\Services\InvestorLedger;
use App\Modules\Portal\Services\InvestorPortal;
use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalContext;
use App\Support\Ajax;
use App\Support\Export\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** The investor's own ledger: capital and profit as separate running balances. */
class StatementController extends Controller
{
    public function index(PortalContext $context): View
    {
        abort_unless($context->allows('investor.portal.investments.view'), 403);

        return view('portal.investor.statements', [
            'balances' => InvestorLedger::balances($context->investor),
            'flats' => Flat::query()->whereIn('id', PortalAccess::investorFlatIds($context->investor))->with('project:id,code')->orderBy('flat_no')->get(['id', 'public_id', 'flat_no', 'project_id'])
                ->mapWithKeys(fn ($f) => [$f->public_id => $f->project->code.' · Unit '.$f->flat_no])->all(),
        ]);
    }

    public function table(Request $request, PortalContext $context): JsonResponse
    {
        abort_unless($context->allows('investor.portal.investments.view'), 403);
        $page = max(1, (int) $request->input('page', 1));
        $rows = $this->query($request, $context)->forPage($page, 25)->get();
        $total = $this->scope($request, $context)->count();

        return Ajax::table(view('portal.investor._ledger_rows', ['rows' => $rows])->render(), new LengthAwarePaginator($rows, $total, 25, $page));
    }

    public function export(Request $request, PortalContext $context): Response
    {
        abort_unless($context->allows('investor.portal.statements.export'), 403);
        PortalAccess::audit($context->user, 'investor', 'export', null, 'ok', $request);
        $flat = $this->flat($request, $context);

        return Report::make('Investor Statement')
            ->meta(['Investor' => $context->investor->name.' ('.$context->investor->investor_code.')', 'Unit' => $flat ? 'Unit '.$flat->flat_no : null, 'Basis' => 'Posted entries'])
            ->columns(['Date' => 'date', 'Entry' => 'text', 'Description' => 'text', 'Capital' => 'money', 'Profit' => 'money', 'Capital Balance' => 'money', 'Profit Due' => 'money'])
            ->rows(fn () => $this->query($request, $context)->lazy()->map(fn ($r) => [$r->entry_date, InvestorLedgerEntry::KINDS[$r->entry_kind] ?? $r->entry_kind, $r->description,
                (float) $r->capital_effect ? $r->capital_effect : null, (float) $r->profit_effect ? $r->profit_effect : null, $r->capital_balance, $r->profit_balance]))
            ->respond($request);
    }

    private function flat(Request $request, PortalContext $context): ?Flat
    {
        return $request->filled('flat') ? (new InvestorPortal($context))->flat((string) $request->input('flat')) : null;
    }

    private function scope(Request $request, PortalContext $context): Builder
    {
        $flat = $this->flat($request, $context);

        return InvestorLedgerEntry::query()->from('investor_ledger_entries as e')->leftJoin('investor_ledger_entries as o', 'o.id', '=', 'e.reversal_of_id')
            ->where('e.investor_id', $context->investor->id)->when($flat, fn ($q) => $q->where('e.flat_id', $flat->id));
    }

    private function query(Request $request, PortalContext $context): Builder
    {
        $capital = "CASE COALESCE(o.entry_kind, e.entry_kind) WHEN 'capital_in' THEN e.amount WHEN 'capital_out' THEN -e.amount ELSE 0 END";
        $profit = "CASE COALESCE(o.entry_kind, e.entry_kind) WHEN 'profit_payable' THEN e.amount WHEN 'profit_paid' THEN -e.amount ELSE 0 END";

        return $this->scope($request, $context)->select('e.id', 'e.entry_date', 'e.entry_kind', 'e.description')
            ->selectRaw("{$capital} AS capital_effect, {$profit} AS profit_effect")
            ->selectRaw("SUM({$capital}) OVER (ORDER BY e.entry_date, e.id) AS capital_balance, SUM({$profit}) OVER (ORDER BY e.entry_date, e.id) AS profit_balance")
            ->orderBy('e.entry_date')->orderBy('e.id');
    }
}
