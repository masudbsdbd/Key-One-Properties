<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FinancialEvent;
use App\Modules\Accounting\Services\EventPoster;
use App\Modules\Accounting\Services\Reconciliation;
use App\Modules\Accounting\Support\AccountingSettings;
use App\Support\Ajax;
use App\Support\FinancialEvents\FinancialEventTypes;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('accounting.reconciliation.view'), 403);

        return view('accounting.reconciliation.index', ['enabled' => AccountingSettings::enabled(), 'start' => AccountingSettings::startDate()]);
    }

    public function checks(Request $request, Reconciliation $reconciliation): JsonResponse
    {
        abort_unless($request->user()->can('accounting.reconciliation.view'), 403);

        return Ajax::success(null, ['html' => view('accounting.reconciliation._checks', [
            'checks' => $reconciliation->checks(), 'canLedger' => $request->user()->can('accounting.ledger.view'),
        ])->render()]);
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accounting.reconciliation.view'), 403);
        $query = EventPoster::backlog()->with(['source', 'project'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('processing_status', $s));
        $rows = TableQuery::apply($query, $request, ['event_type', 'last_error'], ['date' => 'effective_at'], 'date', 'asc');

        return Ajax::table(view('accounting.reconciliation._rows', [
            'events' => $rows, 'labels' => FinancialEventTypes::TYPES, 'canRun' => $request->user()->can('accounting.reconciliation.run'),
        ])->render(), $rows);
    }

    public function retry(Request $request, EventPoster $poster, string $event): JsonResponse
    {
        abort_unless($request->user()->can('accounting.reconciliation.run'), 403);
        $record = EventPoster::backlog()->where('event_uuid', $event)->firstOrFail();
        $poster->attempt($record);
        $record->refresh();
        activity('accounting')->event('posting_retried')->causedBy($request->user())->withProperties(['name' => $record->event_type, 'attributes' => ['result' => $record->processing_status]])->log('posting_retried');

        return $record->processing_status === FinancialEvent::STATUS_FAILED
            ? Ajax::error($record->last_error ?: 'This transaction could not be posted.', 409)
            : Ajax::success('Posted.');
    }

    public function postAll(Request $request, EventPoster $poster): JsonResponse
    {
        abort_unless($request->user()->can('accounting.reconciliation.run'), 403);
        $result = $poster->postBacklog(2000);
        activity('accounting')->event('backlog_posted')->causedBy($request->user())->withProperties(['name' => 'Posting backlog', 'attributes' => $result])->log('backlog_posted');

        return Ajax::success($result['posted'].' posted'.($result['failed'] ? ', '.$result['failed'].' still need attention' : '').'.');
    }
}
