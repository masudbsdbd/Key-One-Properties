<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\MoneyAccount;
use App\Models\MoneyTransaction;
use App\Modules\Accounting\Services\Balances;
use App\Modules\Accounting\Services\CashBank;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Support\MoneyAccounts;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\Money;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CashBankController extends Controller
{
    private const DIRECTIONS = ['in', 'out', 'transfer'];

    public function __construct(private CashBank $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('accounting.cashbank.view'), 403);

        return view('accounting.cash-bank.index', ['accounts' => MoneyAccount::query()->orderBy('name')->get()]);
    }

    public function accounts(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accounting.cashbank.view'), 403);
        $net = Balances::byAccount();
        $accounts = MoneyAccount::query()->orderByDesc('is_active')->orderBy('name')->get()
            ->each(fn (MoneyAccount $a) => $a->setAttribute('balance', Money::amount($net[$a->ledger_account_id] ?? '0')));

        return Ajax::success(null, ['html' => view('accounting.cash-bank._accounts', [
            'accounts' => $accounts,
            'totals' => collect(MoneyAccount::KINDS)->map(fn ($label, $kind) => Money::add('0', ...$accounts->where('kind', $kind)->pluck('balance')->all())),
            'canManage' => $request->user()->can('accounting.cashbank.manage'),
        ])->render()]);
    }

    public function show(Request $request, MoneyAccount $account): View
    {
        abort_unless($request->user()->can('accounting.cashbank.view'), 403);

        return view('accounting.cash-bank.show', ['account' => $account, 'balance' => $this->service->balance($account)]);
    }

    public function balance(Request $request, MoneyAccount $account): JsonResponse
    {
        abort_unless($request->user()->can('accounting.cashbank.view'), 403);

        return Ajax::success(null, ['html' => view('accounting.cash-bank._balance', ['balance' => $this->service->balance($account)])->render()]);
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accounting.cashbank.view'), 403);
        $account = MoneyAccount::findByPublicId($request->input('account'));
        $query = $this->listing($request, $account);
        $rows = TableQuery::paginate($query->orderByDesc('journal_entries.accounting_date')->orderByDesc('journal_lines.id'), $request);

        // Running balance for a single account: the balance after the newest row on the page, then walked down.
        if ($account && $rows->isNotEmpty()) {
            $first = $rows->first();
            $balance = Money::amount(Balances::lines(where: ['money_account_id' => $account->id])
                ->where(fn (Builder $q) => $q->where('journal_entries.accounting_date', '<', $first->accounting_date)
                    ->orWhere(fn (Builder $q) => $q->where('journal_entries.accounting_date', $first->accounting_date)->where('journal_lines.id', '<=', $first->id)))
                ->selectRaw('COALESCE(SUM(journal_lines.debit) - SUM(journal_lines.credit), 0) AS net')->value('net'));
            foreach ($rows as $row) {
                $row->setAttribute('balance', $balance);
                $balance = Money::sub(Money::add($balance, (string) $row->credit), (string) $row->debit);
            }
        }

        return Ajax::table(view('accounting.cash-bank._rows', [
            'lines' => $rows, 'single' => (bool) $account,
            'canManage' => $request->user()->can('accounting.cashbank.manage'), 'canJournal' => $request->user()->can('accounting.journal.view'),
        ])->render(), $rows);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('accounting.cashbank.view') && $request->user()->can('accounting.reports.export'), 403);
        $account = MoneyAccount::findByPublicId($request->input('account'));
        $query = $this->listing($request, $account)->orderBy('journal_entries.accounting_date')->orderBy('journal_lines.id');

        return Report::make($account ? 'Statement · '.$account->name : 'Money In and Out')->sumMoney()
            ->meta(['Period' => TableQuery::periodLabel($request), 'Account' => $account?->label()])
            ->columns(['Date' => 'date', 'Journal' => 'text', 'Account' => 'text', 'Details' => 'text', 'In' => 'money', 'Out' => 'money'])
            ->rows(fn () => $query->lazy()->map(fn (JournalLine $l) => [$l->entry->accounting_date, $l->entry->journal_no, $l->moneyAccount->name, $l->description ?: $l->entry->narration,
                (float) $l->debit ? $l->debit : null, (float) $l->credit ? $l->credit : null]))
            ->respond($request);
    }

    /** Posted lines on cash and bank accounts, with the filters of the list. */
    private function listing(Request $request, ?MoneyAccount $account): Builder
    {
        $query = JournalLine::query()->select('journal_lines.*', 'journal_entries.accounting_date')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')->whereNotNull('journal_lines.money_account_id')
            ->when($request->filled('account'), fn (Builder $q) => $q->where('journal_lines.money_account_id', $account?->id ?? 0))
            ->when($request->input('direction'), fn (Builder $q, $d) => $q->where($d === 'in' ? 'journal_lines.debit' : 'journal_lines.credit', '>', 0))
            ->with(['entry.source', 'moneyAccount', 'customer', 'supplier', 'contractor', 'investor']);
        TableQuery::period($query, $request, 'journal_entries.accounting_date');
        $term = trim((string) $request->input('search'));
        if ($term !== '') {
            $like = '%'.addcslashes(mb_substr($term, 0, 100), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('journal_entries.narration', 'like', $like)->orWhere('journal_entries.journal_no', 'like', $like)->orWhere('journal_entries.reference', 'like', $like));
        }

        return $query;
    }

    /** Accounts for the payment and receipt forms of the other modules: names only, never balances. */
    public function options(Request $request): JsonResponse
    {
        if (! $request->user()->canAny(MoneyAccounts::PAYMENT_PERMISSIONS)) {
            return Ajax::options([]);
        }
        $kind = MoneyAccounts::KIND_FOR_METHOD[(string) $request->input('method')] ?? null;
        $query = MoneyAccount::query()->active()->orderBy('name')->when($kind, fn (Builder $q) => $q->where('kind', $kind));
        [$results, $more] = TableQuery::options($query, $request, ['name', 'account_number'], fn (MoneyAccount $a) => ['id' => $a->public_id, 'text' => $a->label()]);

        return Ajax::options($results, $more);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accounting.cashbank.manage'), 403);

        return Ajax::success(null, ['title' => 'New Account', 'html' => view('accounting.cash-bank._account-form', ['account' => new MoneyAccount(['kind' => 'bank', 'is_active' => true])])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accounting.cashbank.manage'), 403);
        $data = $this->accountData($request);
        $data['opening_date'] = Carbon::parse($data['opening_date'] ?? Ledger::today());
        $account = $this->service->createAccount($data, $request->user());
        $this->audit($request, $account, 'money_account_created', ['name' => $account->name]);

        return Ajax::success($account->name.' added.', ['redirect' => route('accounting.cash-bank.index')]);
    }

    public function edit(Request $request, MoneyAccount $account): JsonResponse
    {
        abort_unless($request->user()->can('accounting.cashbank.manage'), 403);

        return Ajax::success(null, ['title' => 'Edit '.$account->name, 'html' => view('accounting.cash-bank._account-form', ['account' => $account])->render()]);
    }

    public function update(Request $request, MoneyAccount $account): JsonResponse
    {
        abort_unless($request->user()->can('accounting.cashbank.manage'), 403);
        $data = $this->accountData($request, $account);
        if (! $data['is_active'] && Money::cmp($this->service->balance($account), 0) !== 0) {
            return Ajax::error($account->name.' still has a balance. Move it out first.', 409);
        }
        $this->service->updateAccount($account, $data);
        $this->audit($request, $account, 'money_account_updated', ['name' => $account->name, 'active' => $account->is_active]);

        return Ajax::success($account->name.' saved.', ['redirect' => route('accounting.cash-bank.show', $account)]);
    }

    public function destroy(Request $request, MoneyAccount $account): JsonResponse
    {
        abort_unless($request->user()->can('accounting.cashbank.manage'), 403);
        if ($account->isUsed()) {
            return Ajax::error($account->name.' has transactions and cannot be deleted.', 409);
        }
        $this->audit($request, $account, 'money_account_deleted', ['name' => $account->name]);
        $this->service->deleteAccount($account);

        return Ajax::success('Account deleted.', ['redirect' => route('accounting.cash-bank.index')]);
    }

    public function moveForm(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accounting.cashbank.manage'), 403);
        $direction = (string) $request->input('direction');
        abort_unless(in_array($direction, self::DIRECTIONS, true), 404);
        $account = MoneyAccount::findByPublicId($request->input('account'));

        return Ajax::success(null, ['title' => ['in' => 'Deposit', 'out' => 'Withdraw', 'transfer' => 'Transfer'][$direction].($account ? ' · '.$account->name : ''),
            'html' => view('accounting.cash-bank._move-form', [
                'direction' => $direction, 'account' => $account, 'key' => (string) Str::uuid(),
                'accounts' => MoneyAccount::query()->active()->orderBy('name')->get(),
                'canPickAccount' => $request->user()->can('accounting.journal.create'),
            ])->render()]);
    }

    public function move(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accounting.cashbank.manage'), 403);
        $direction = (string) $request->input('direction');
        abort_unless(in_array($direction, self::DIRECTIONS, true), 404);
        $request->merge(['amount' => str_replace(',', '', (string) $request->input('amount')), 'charge' => str_replace(',', '', (string) $request->input('charge')) ?: null]);
        $data = $request->validate([
            'account' => ['required', 'string', 'size:16'],
            'other' => ['required', 'string', 'max:60'],
            'counter' => ['nullable', 'string', 'size:16'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999.99', 'decimal:0,2'],
            'charge' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'date' => ['required', 'date', 'before_or_equal:'.Ledger::today()->toDateString()],
            'party_name' => ['nullable', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ], ['date.before_or_equal' => 'The date cannot be in the future.']);

        $own = fn (?string $id, string $field) => MoneyAccount::query()->active()->where('public_id', (string) $id)->first()
            ?? throw ValidationException::withMessages([$field => 'Select an active account.']);
        $account = $own($data['account'], 'account');
        [$kind, $value] = array_pad(explode(':', $data['other'], 2), 2, '');
        $base = ['amount' => (string) $data['amount'], 'date' => Carbon::parse($data['date']), 'reference' => $data['reference'] ?? null, 'description' => $data['description'] ?? null, 'key' => $data['idempotency_key']];

        if ($kind === 'account') {
            $other = $own($value, 'other');
            [$from, $to] = $direction === 'in' ? [$other, $account] : [$account, $other];
            $txn = $this->service->transfer($from, $to, $base + ['charge' => $data['charge'] ?? null], $request->user());
        } else {
            abort_if($direction === 'transfer', 422, 'Choose one of your accounts.');
            $txn = $this->service->move($direction === 'in' ? 'money_in' : 'money_out', $account, $base + [
                'purpose' => $value, 'party_name' => $data['party_name'] ?? null,
                'counter' => $value === 'other' && $request->user()->can('accounting.journal.create') ? LedgerAccount::query()->postable()->where('public_id', (string) ($data['counter'] ?? ''))->first() : null,
            ], $request->user());
        }
        $this->audit($request, $txn, 'money_'.$txn->type, ['amount' => (string) $txn->amount, 'name' => $txn->txn_no]);

        return Ajax::success($txn->txn_no.' recorded.');
    }

    public function cancelForm(Request $request, MoneyTransaction $transaction): JsonResponse
    {
        abort_unless($request->user()->can('accounting.cashbank.manage'), 403);

        return Ajax::success(null, ['title' => 'Cancel '.$transaction->txn_no, 'html' => view('accounting.cash-bank._cancel', ['txn' => $transaction])->render()]);
    }

    public function cancel(Request $request, MoneyTransaction $transaction): JsonResponse
    {
        abort_unless($request->user()->can('accounting.cashbank.manage'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->service->cancel($transaction, $data['reason'], $request->user());
        $this->audit($request, $transaction, 'money_cancelled', ['name' => $transaction->txn_no, 'reason' => $data['reason']]);

        return Ajax::success($transaction->txn_no.' cancelled.');
    }

    private function accountData(Request $request, ?MoneyAccount $account = null): array
    {
        $request->merge(['opening_balance' => str_replace(',', '', (string) $request->input('opening_balance')) ?: null, 'is_active' => $request->boolean('is_active', true)]);

        return $request->validate([
            'kind' => [Rule::requiredIf(! $account), Rule::in(array_keys(MoneyAccount::KINDS))],
            'name' => ['required', 'string', 'max:120', Rule::unique('money_accounts')->ignore($account?->id)],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9 \-]+$/'],
            'branch' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99', 'decimal:0,2'],
            'opening_date' => ['nullable', 'date', 'before_or_equal:'.Ledger::today()->toDateString()],
            'is_active' => ['boolean'],
        ], ['account_number.regex' => 'Use letters, numbers, spaces and dashes only.', 'name.unique' => 'An account with this name already exists.']);
    }

    private function audit(Request $request, $subject, string $event, array $attributes): void
    {
        activity('accounting')->event($event)->causedBy($request->user())->performedOn($subject)
            ->withProperties(['name' => $attributes['name'] ?? null, 'attributes' => $attributes])->log($event);
    }
}
