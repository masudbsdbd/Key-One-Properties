<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Models\MoneyAccount;
use App\Modules\Accounting\Services\Cheques;
use App\Modules\Accounting\Services\Ledger;
use App\Support\Ajax;
use App\Support\Money;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChequeController extends Controller
{
    private const ACTIONS = ['deposit' => 'Deposit', 'clear' => 'Mark Cleared', 'bounce' => 'Mark Bounced'];

    public function __construct(private Cheques $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->canAny(['accounting.cashbank.view', 'accounting.cheque.manage']), 403);

        return view('accounting.cheques.index');
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['accounting.cashbank.view', 'accounting.cheque.manage']), 403);
        $query = Cheque::query()->with(['customer', 'moneyAccount', 'source'])
            ->when($request->input('status', 'open') !== 'all', fn (Builder $q) => $request->input('status', 'open') === 'open'
                ? $q->whereIn('status', Cheque::OPEN) : $q->where('status', (string) $request->input('status')));
        TableQuery::period($query, $request, 'received_on');
        $rows = TableQuery::apply($query, $request, ['cheque_no', 'bank_name', 'customer.full_name'], ['date' => 'cheque_date', 'received' => 'received_on', 'amount' => 'amount'], 'received', 'desc');
        $totals = Cheque::query()->whereIn('status', Cheque::OPEN)->groupBy('status')->selectRaw('status, COUNT(*) AS n, SUM(amount) AS total')->get()->keyBy('status');

        return Ajax::table(view('accounting.cheques._rows', ['cheques' => $rows, 'canManage' => $request->user()->can('accounting.cheque.manage')])->render(), $rows, [
            'stats' => view('accounting.cheques._stats', ['totals' => $totals])->render(),
        ]);
    }

    public function form(Request $request, Cheque $cheque, string $action): JsonResponse
    {
        abort_unless($request->user()->can('accounting.cheque.manage'), 403);

        return Ajax::success(null, ['title' => self::ACTIONS[$action].' · Cheque '.$cheque->cheque_no, 'html' => view('accounting.cheques._action', [
            'cheque' => $cheque->load(['customer', 'moneyAccount']), 'action' => $action,
            'accounts' => MoneyAccount::query()->active()->where('kind', 'bank')->orderBy('name')->get()->mapWithKeys(fn ($a) => [$a->public_id => $a->label()]),
        ])->render()]);
    }

    public function update(Request $request, Cheque $cheque, string $action): JsonResponse
    {
        abort_unless($request->user()->can('accounting.cheque.manage'), 403);
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:'.$cheque->received_on->toDateString(), 'before_or_equal:'.Ledger::today()->toDateString()],
            'money_account' => [Rule::requiredIf($action !== 'bounce'), 'nullable', 'string', 'size:16'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['date.after_or_equal' => 'The date cannot be before the cheque was received.', 'date.before_or_equal' => 'The date cannot be in the future.']);
        $date = Carbon::parse($data['date']);
        $account = $action === 'bounce' ? null : (MoneyAccount::findByPublicId($data['money_account']) ?? throw ValidationException::withMessages(['money_account' => 'Choose an active bank account.']));

        match ($action) {
            'deposit' => $this->service->deposit($cheque, $account, $date, $request->user()),
            'clear' => $this->service->clear($cheque, $account, $date, $request->user()),
            'bounce' => $this->service->bounce($cheque, $date, $data['note'] ?? null, $request->user()),
        };
        activity('accounting')->event('cheque_'.$action)->causedBy($request->user())->performedOn($cheque)
            ->withProperties(['name' => 'Cheque '.$cheque->cheque_no, 'attributes' => ['amount' => Money::amount($cheque->amount), 'account' => $account?->name]])->log('cheque_'.$action);

        return Ajax::success('Cheque '.$cheque->cheque_no.' '.['deposit' => 'deposited', 'clear' => 'cleared', 'bounce' => 'bounced'][$action].'.');
    }
}
