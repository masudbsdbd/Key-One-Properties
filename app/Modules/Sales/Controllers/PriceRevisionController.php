<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Flat;
use App\Models\FlatPriceRevision;
use App\Models\Project;
use App\Modules\Sales\Services\PriceRevisionService;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PriceRevisionController extends Controller
{
    public function __construct(private PriceRevisionService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('sales.price.view'), 403);

        return view('sales.price-revisions.index');
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.price.view'), 403);
        $query = FlatPriceRevision::query()->whereIn('flat_id', Flat::query()->visibleTo($request->user())->select('flats.id'))
            ->with(['flat:id,public_id,flat_no,project_id,building_id', 'flat.project:id,code', 'flat.building:id,code', 'requester:id,name', 'approver:id,name']);
        if (array_key_exists($status = (string) $request->input('status'), FlatPriceRevision::STATUSES)) {
            $query->where('status', $status);
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->whereHas('flat', fn ($q) => $q->where('project_id', $project->id));
        }
        $rows = TableQuery::apply($query, $request, ['reason', 'flat.flat_no'], ['date' => 'created_at'], 'date', 'desc');

        return Ajax::table(view('sales.price-revisions._rows', ['revisions' => $rows])->render(), $rows);
    }

    public function approve(Request $request, FlatPriceRevision $revision): JsonResponse
    {
        $this->authorizeDecision($request, $revision);
        $this->service->approve($revision, $request->user());

        return Ajax::success('Price updated.');
    }

    public function rejectForm(Request $request, FlatPriceRevision $revision): JsonResponse
    {
        $this->authorizeDecision($request, $revision);

        return Ajax::success(null, ['title' => 'Reject price change', 'html' => view('partials.reason-form', [
            'action' => route('price-revisions.reject', $revision), 'button' => 'Reject', 'reload' => true,
        ])->render()]);
    }

    public function reject(Request $request, FlatPriceRevision $revision): JsonResponse
    {
        $this->authorizeDecision($request, $revision);
        $this->service->reject($revision, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Price change rejected.');
    }

    private function authorizeDecision(Request $request, FlatPriceRevision $revision): void
    {
        abort_unless($request->user()->can('sales.price.change.approve') && $revision->flat->isVisibleTo($request->user()), 403);
    }
}
