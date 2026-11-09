<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\Project;
use App\Models\SalesCancellation;
use App\Modules\Sales\Services\SalesService;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        abort_unless($request->user()->canAny(['sales.booking.cancel.request', 'sales.booking.cancel.approve']), 403);
        $visible = Project::query()->visibleTo($request->user())->select('projects.id');
        $query = SalesCancellation::query()->with(['booking.flat', 'booking.customer', 'sale.flat', 'sale.customer', 'requester'])
            ->where(fn (Builder $q) => $q->whereHas('booking', fn ($b) => $b->whereIn('project_id', $visible))->orWhereHas('sale', fn ($s) => $s->whereIn('project_id', $visible)));
        if (array_key_exists($status = (string) $request->input('status'), SalesCancellation::STATUSES)) {
            $query->where('status', $status);
        }
        $rows = TableQuery::apply($query, $request, ['cancellation_no', 'reason'], ['date' => 'created_at'], 'date', 'desc');

        return Ajax::table(view('sales.cancellations._rows', ['cancellations' => $rows])->render(), $rows);
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
