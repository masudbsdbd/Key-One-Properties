<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Modules\Accounting\Services\BankReconciler;
use App\Modules\Accounting\Services\CashBank;
use App\Modules\Accounting\Services\Ledger;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BankReconciliationController extends Controller
{
    public function __construct(private BankReconciler $service) {}

    public function index(Request $request, MoneyAccount $account): View
    {
        abort_unless($request->user()->can('accounting.bankreconciliation.manage'), 403);

        return view('accounting.bank-reconciliation.index', [
            'account' => $account,
            'balance' => app(CashBank::class)->balance($account),
            'reconciliations' => BankReconciliation::query()->where('money_account_id', $account->id)->with(['completer'])->orderByDesc('statement_date')->limit(60)->get(),
        ]);
    }

    public function store(Request $request, MoneyAccount $account): JsonResponse
    {
        abort_unless($request->user()->can('accounting.bankreconciliation.manage'), 403);
        $request->merge(['statement_balance' => str_replace(',', '', (string) $request->input('statement_balance'))]);
        $data = $request->validate([
            'statement_date' => ['required', 'date', 'before_or_equal:'.Ledger::today()->toDateString()],
            'statement_balance' => ['required', 'numeric', 'min:-9999999999999999.99', 'max:9999999999999999.99', 'decimal:0,2'],
        ], ['statement_date.before_or_equal' => 'The statement date cannot be in the future.']);
        $reconciliation = $this->service->start($account, Carbon::parse($data['statement_date']), (string) $data['statement_balance'], $request->user());

        return Ajax::success(null, ['redirect' => route('accounting.bank-reconciliation.show', $reconciliation)]);
    }

    public function show(Request $request, BankReconciliation $reconciliation): View
    {
        abort_unless($request->user()->can('accounting.bankreconciliation.manage'), 403);
        $reconciliation->load('moneyAccount');
        $lines = $reconciliation->status === 'draft'
            ? $this->service->lines($reconciliation)->with('entry')->orderBy('journal_entries.accounting_date')->orderBy('journal_lines.id')->limit(2000)->get()
            : JournalLine::query()->select('journal_lines.*', 'journal_entries.accounting_date')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_lines.bank_reconciliation_id', $reconciliation->id)->with('entry')->orderBy('journal_entries.accounting_date')->orderBy('journal_lines.id')->get();

        return view('accounting.bank-reconciliation.show', ['reconciliation' => $reconciliation, 'lines' => $lines, 'summary' => $this->service->summary($reconciliation)]);
    }

    /** Tick or untick entries. A line is named by its journal's public id and line number, never the row id. */
    public function tick(Request $request, BankReconciliation $reconciliation): JsonResponse
    {
        abort_unless($request->user()->can('accounting.bankreconciliation.manage'), 403);
        $data = $request->validate(['lines' => ['required', 'array', 'max:2000'], 'lines.*' => ['string', 'regex:/^[a-z0-9]{16}-\d{1,5}$/'], 'ticked' => ['required', 'boolean']]);
        $pairs = collect($data['lines'])->map(fn (string $token) => explode('-', $token));
        $journals = JournalEntry::query()->whereIn('public_id', $pairs->pluck(0)->unique())->pluck('id', 'public_id');
        $ids = [];
        foreach ($pairs->groupBy(0) as $publicId => $group) {
            if (isset($journals[$publicId])) {
                array_push($ids, ...JournalLine::query()->where('journal_entry_id', $journals[$publicId])->whereIn('line_no', $group->pluck(1))->pluck('id')->all());
            }
        }
        $this->service->tick($reconciliation, $ids, (bool) $data['ticked']);

        return Ajax::success(null, ['html' => view('accounting.bank-reconciliation._summary', ['reconciliation' => $reconciliation, 'summary' => $this->service->summary($reconciliation)])->render()]);
    }

    public function complete(Request $request, BankReconciliation $reconciliation): JsonResponse
    {
        abort_unless($request->user()->can('accounting.bankreconciliation.manage'), 403);
        $this->service->complete($reconciliation, $request->user());
        activity('accounting')->event('bank_reconciled')->causedBy($request->user())->performedOn($reconciliation)
            ->withProperties(['name' => $reconciliation->moneyAccount->name, 'attributes' => ['statement_date' => $reconciliation->statement_date->toDateString(), 'balance' => (string) $reconciliation->statement_balance]])->log('bank_reconciled');

        return Ajax::success('Reconciled to the statement of '.$reconciliation->statement_date->format('j M Y').'.', ['redirect' => route('accounting.bank-reconciliation.index', $reconciliation->moneyAccount)]);
    }

    public function destroy(Request $request, BankReconciliation $reconciliation): JsonResponse
    {
        abort_unless($request->user()->can('accounting.bankreconciliation.manage'), 403);
        $account = $reconciliation->moneyAccount;
        $this->service->discard($reconciliation);

        return Ajax::success('Reconciliation removed.', ['redirect' => route('accounting.bank-reconciliation.index', $account)]);
    }
}
