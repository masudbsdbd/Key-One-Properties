<?php

namespace App\Modules\Portal\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerStatementEntry;
use App\Modules\Collections\Services\CustomerStatement;
use App\Modules\Portal\Services\CustomerPortal;
use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalContext;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StatementController extends Controller
{
    public function index(PortalContext $context): View
    {
        abort_unless($context->allows('customer.portal.statements.download'), 403);

        return view('portal.customer.statement', ['balance' => CustomerStatement::balance($context->customer)]);
    }

    public function table(Request $request, PortalContext $context): JsonResponse
    {
        abort_unless($context->allows('customer.portal.statements.download'), 403);
        $page = max(1, (int) $request->input('page', 1));
        $rows = $this->query($context)->forPage($page, 25)->get();

        return Ajax::table(view('collections.statements._rows', ['rows' => $rows])->render(), new LengthAwarePaginator($rows, CustomerPortal::for($context)->statement()->count(), 25, $page));
    }

    public function export(Request $request, PortalContext $context): Response
    {
        abort_unless($context->allows('customer.portal.statements.download'), 403);
        PortalAccess::audit($context->user, 'customer', 'export', null, 'ok', $request);
        $c = $context->customer;

        return Report::make('Customer Statement')
            ->meta(['Customer' => $c->full_name.' ('.$c->customer_code.')', 'Balance' => Format::money(CustomerStatement::balance($c)), 'Basis' => 'Posted entries'])
            ->columns(['Date' => 'date', 'Type' => 'text', 'Description' => 'text', 'Charge' => 'money', 'Credit' => 'money', 'Balance' => 'money'])
            ->rows(fn () => $this->query($context)->lazy()->map(fn ($r) => [$r->entry_date, CustomerStatementEntry::TYPES[$r->entry_type] ?? $r->entry_type, $r->description,
                (float) $r->charge_amount ? $r->charge_amount : null, (float) $r->credit_amount ? $r->credit_amount : null, $r->running_balance]))
            ->respond($request);
    }

    private function query(PortalContext $context): Builder
    {
        return CustomerPortal::for($context)->statement()->select('*')
            ->selectRaw('SUM(charge_amount - credit_amount) OVER (ORDER BY entry_date, id) AS running_balance')->orderBy('entry_date')->orderBy('id');
    }
}
