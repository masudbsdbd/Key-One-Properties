<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FinancialEvent;
use App\Models\MoneyAccount;
use App\Modules\Accounting\Services\EventPoster;
use App\Modules\Accounting\Services\Reconciliation;
use App\Modules\Accounting\Support\AccountingSettings;
use App\Modules\Accounting\Support\MoneyAccounts;
use App\Support\Ajax;
use App\Support\FinancialEvents\FinancialEventTypes;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

    /** A payment waiting only for its account: choose it here and post. */
    public function accountForm(Request $request, string $event): JsonResponse
    {
        abort_unless($request->user()->can('accounting.reconciliation.run'), 403);
        $record = EventPoster::backlog()->where('event_uuid', $event)->with('source')->firstOrFail();
        abort_unless(self::needsAccount($record), 404);
        $kind = MoneyAccounts::KIND_FOR_METHOD[(string) self::methodOf($record->source)] ?? null;

        return Ajax::success(null, ['title' => 'Choose Account', 'html' => view('accounting.reconciliation._account', [
            'event' => $record, 'label' => FinancialEventTypes::TYPES[$record->event_type] ?? $record->event_type,
            'accounts' => MoneyAccount::query()->active()->when($kind, fn ($q) => $q->where('kind', $kind))->orderBy('name')->get()->mapWithKeys(fn ($a) => [$a->public_id => $a->label()]),
        ])->render()]);
    }

    public function setAccount(Request $request, EventPoster $poster, string $event): JsonResponse
    {
        abort_unless($request->user()->can('accounting.reconciliation.run'), 403);
        $record = EventPoster::backlog()->where('event_uuid', $event)->with('source')->firstOrFail();
        abort_unless(self::needsAccount($record), 404);
        $data = $request->validate(['money_account' => ['required', 'string', 'size:16']]);
        $kind = MoneyAccounts::KIND_FOR_METHOD[(string) self::methodOf($record->source)] ?? null;
        $account = MoneyAccount::query()->active()->where('public_id', $data['money_account'])->when($kind, fn ($q) => $q->where('kind', $kind))->first()
            ?? throw ValidationException::withMessages(['money_account' => 'Choose an active '.($kind ? strtolower(MoneyAccount::KINDS[$kind]).' ' : '').'account.']);

        // Only the account is recorded on the document; nothing else about it changes.
        $record->source->forceFill(['money_account_id' => $account->id])->saveQuietly();
        activity('accounting')->event('payment_account_set')->causedBy($request->user())->performedOn($record->source)
            ->withProperties(['name' => class_basename($record->source), 'attributes' => ['account' => $account->name]])->log('payment_account_set');
        // Every waiting entry of the same document can post now.
        EventPoster::backlog()->where('source_type', $record->source_type)->where('source_id', $record->source_id)->orderBy('id')->get()->each(fn (FinancialEvent $e) => $poster->attempt($e));
        $record->refresh();

        return $record->processing_status === FinancialEvent::STATUS_FAILED
            ? Ajax::error($record->last_error ?: 'This transaction could not be posted.', 409)
            : Ajax::success('Posted to '.$account->name.'.');
    }

    public static function needsAccount(FinancialEvent $event): bool
    {
        return $event->source !== null && array_key_exists('money_account_id', $event->source->getAttributes()) && $event->source->money_account_id === null;
    }

    private static function methodOf(Model $source): ?string
    {
        return $source->getAttribute('payment_method') ?? $source->getAttribute('method');
    }

    public function postAll(Request $request, EventPoster $poster): JsonResponse
    {
        abort_unless($request->user()->can('accounting.reconciliation.run'), 403);
        $result = $poster->postBacklog(2000);
        activity('accounting')->event('backlog_posted')->causedBy($request->user())->withProperties(['name' => 'Posting backlog', 'attributes' => $result])->log('backlog_posted');

        return Ajax::success($result['posted'].' posted'.($result['failed'] ? ', '.$result['failed'].' still need attention' : '').'.');
    }
}
