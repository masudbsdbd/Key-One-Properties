<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\Project;
use App\Models\SalesCancellation;
use App\Modules\Sales\Services\SalesService;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\ListSummary;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CancellationController extends Controller
{
    public function __construct(private SalesService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->canAny(['sales.booking.cancel.request', 'sales.booking.cancel.approve']), 403);

        return view('sales.cancellations.index');
    }

    public function table(Request $request): JsonResponse
    {
        $query = $this->listing($request);
        $rows = TableQuery::apply(clone $query, $request, ['cancellation_no', 'reason'], ['date' => 'created_at'], 'date', 'desc');
        $row = ListSummary::aggregate($this->listing(ListSummary::without($request)), [
            'requested' => "SUM(CASE WHEN sales_cancellations.status = 'requested' THEN 1 ELSE 0 END)",
            'approved' => "SUM(CASE WHEN sales_cancellations.status = 'approved' THEN 1 ELSE 0 END)",
            'refund' => "SUM(CASE WHEN sales_cancellations.status = 'approved' THEN COALESCE(sales_cancellations.refund_expected, 0) ELSE 0 END)",
        ]);
        $summary = ListSummary::make($request)
            ->count('Awaiting Decision', $row->requested, 'alert', 'accent', ['status' => 'requested'])
            ->count('Approved', $row->approved, 'check', 'danger', ['status' => 'approved'])
            ->money('Refund Expected', $row->refund, 'restore', 'warning');

        return Ajax::table(view('sales.cancellations._rows', ['cancellations' => $rows])->render(), $rows, ['stats' => $summary->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('sales.export'), 403);

        return Report::make('Cancellations')->sumMoney()->meta(['Status' => SalesCancellation::STATUSES[(string) $request->input('status')] ?? null, 'Period' => TableQuery::periodLabel($request)])
            ->columns(['Cancellation No' => 'text', 'Requested' => 'date', 'Against' => 'text', 'Unit' => 'text', 'Customer' => 'text', 'Reason' => 'text', 'Refund Expected' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->latest('created_at')->lazy()->map(fn (SalesCancellation $c) => [$c->cancellation_no, $c->created_at, $c->sale?->sale_no ?? $c->booking?->booking_no,
                ($c->sale ?? $c->booking)?->flat?->flat_no, ($c->sale ?? $c->booking)?->customer?->full_name, $c->reason, $c->refund_expected, SalesCancellation::STATUSES[$c->status] ?? $c->status]))
            ->respond($request);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->canAny(['sales.booking.cancel.request', 'sales.booking.cancel.approve']), 403);
        $visible = Project::query()->visibleTo($request->user())->select('projects.id');
        $query = SalesCancellation::query()->with(['booking.flat', 'booking.customer', 'sale.flat', 'sale.customer', 'requester'])
            ->where(fn (Builder $q) => $q->whereHas('booking', fn ($b) => $b->whereIn('project_id', $visible))->orWhereHas('sale', fn ($s) => $s->whereIn('project_id', $visible)));
        if (array_key_exists($status = (string) $request->input('status'), SalesCancellation::STATUSES)) {
            $query->where('status', $status);
        }

        return TableQuery::period($query, $request, 'created_at');
    }

    public function approve(Request $request, SalesCancellation $cancellation): JsonResponse
    {
        $this->authorizeDecision($request, $cancellation);
        $refund = $this->service->approveCancellation($cancellation, $request->user());

        return Ajax::success($refund ? 'Cancelled. Refund '.$refund->refund_no.' is waiting for approval.' : 'Cancelled. The unit is available again.');
    }

    public function rejectForm(Request $request, SalesCancellation $cancellation): JsonResponse
    {
        $this->authorizeDecision($request, $cancellation);

        return Ajax::success(null, ['title' => 'Reject '.$cancellation->cancellation_no, 'html' => view('partials.reason-form', [
            'action' => route('cancellations.reject', $cancellation), 'button' => 'Reject', 'reload' => true,
        ])->render()]);
    }

    public function reject(Request $request, SalesCancellation $cancellation): JsonResponse
    {
        $this->authorizeDecision($request, $cancellation);
        $this->service->rejectCancellation($cancellation, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Cancellation rejected.');
    }

    private function authorizeDecision(Request $request, SalesCancellation $cancellation): void
    {
        /** @var FlatBooking|FlatSale $doc */
        $doc = $cancellation->booking ?? $cancellation->sale;
        abort_unless($request->user()->can('sales.booking.cancel.approve') && $doc->isVisibleTo($request->user()), 403);
    }
}
