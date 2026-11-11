<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LedgerAccount;
use App\Modules\Accounting\Services\Balances;
use App\Modules\Accounting\Support\ChartOfAccounts;
use App\Support\Ajax;
use App\Support\Money;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('accounting.coa.view'), 403);

        return view('accounting.accounts.index');
    }

    public function list(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accounting.coa.view'), 403);
        $accounts = LedgerAccount::query()->orderBy('code')->get();
        $net = Balances::byAccount();
        $children = $accounts->groupBy(fn (LedgerAccount $a) => $a->parent_id ?? 0);

        // A group shows the total of the accounts below it.
        $rows = collect();
        $walk = function (int $parent, int $depth) use (&$walk, $children, $rows, $net): string {
            $sum = '0';
            foreach ($children->get($parent, []) as $account) {
                $rows->push($account->setAttribute('depth', $depth));
                $own = Balances::natural($account, $net[$account->id] ?? '0');
                $total = Money::add($own, $walk($account->id, $depth + 1));
                $account->setAttribute('balance', $total);
                $sum = Money::add($sum, $total);
            }

            return $sum;
        };
        $walk(0, 0);

        return Ajax::success(null, ['html' => view('accounting.accounts._list', [
            'groups' => $rows->groupBy('category'),
            'canManage' => $request->user()->can('accounting.coa.manage'),
            'canLedger' => $request->user()->can('accounting.ledger.view'),
        ])->render()]);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accounting.coa.manage'), 403);

        return $this->form(new LedgerAccount(['is_postable' => true, 'is_active' => true, 'category' => $request->input('category')]), 'New Account');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accounting.coa.manage'), 403);
        $data = $this->validated($request);

        $account = new LedgerAccount($data);
        $account->parent_id = $data['parent_id'];
        $account->created_by = $request->user()->id;
        $account->save();
        $this->audit($request, $account, 'account_created');

        return Ajax::success($account->label().' added.');
    }

    public function edit(Request $request, LedgerAccount $account): JsonResponse
    {
        abort_unless($request->user()->can('accounting.coa.manage'), 403);

        return $this->form($account, 'Edit '.$account->code);
    }

    public function update(Request $request, LedgerAccount $account): JsonResponse
    {
        abort_unless($request->user()->can('accounting.coa.manage'), 403);
        $data = $this->validated($request, $account);
        if (! $data['is_active'] && ($account->system_key || $account->moneyAccount()->exists())) {
            return Ajax::error($account->name.' is used by the system and stays active.', 409);
        }

        $before = $account->only(['code', 'name', 'is_active']);
        $account->fill(['name' => $data['name'], 'description' => $data['description'] ?? null, 'is_active' => $data['is_active']]);
        if (! $account->is_system) {
            $account->code = $data['code'];
        }
        $account->save();
        $this->audit($request, $account, 'account_updated', $before);

        return Ajax::success($account->label().' saved.');
    }

    public function destroy(Request $request, LedgerAccount $account): JsonResponse
    {
        abort_unless($request->user()->can('accounting.coa.manage'), 403);
        if ($account->is_system || $account->lines()->exists() || $account->children()->exists() || $account->moneyAccount()->exists()) {
            return Ajax::error($account->label().' is in use and cannot be deleted.', 409);
        }
        $this->audit($request, $account, 'account_deleted');
        $account->delete();

        return Ajax::success('Account deleted.');
    }

    public function options(Request $request): JsonResponse
    {
        if (! $request->user()->canAny(['accounting.coa.view', 'accounting.journal.create', 'accounting.cashbank.manage', 'accounting.ledger.view', 'accounting.openingbalance.manage'])) {
            return Ajax::options([]);
        }
        $query = LedgerAccount::query()->postable()->orderBy('code')
            ->when($request->input('category'), fn ($q, $c) => $q->where('category', $c));
        [$results, $more] = TableQuery::options($query, $request, ['code', 'name'], fn (LedgerAccount $a) => ['id' => $a->public_id, 'text' => $a->label()]);

        return Ajax::options($results, $more);
    }

    private function validated(Request $request, ?LedgerAccount $account = null): array
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code'))), 'is_active' => $request->boolean('is_active', true)]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-\.]+$/', Rule::unique('ledger_accounts')->ignore($account?->id)],
            'name' => ['required', 'string', 'max:120'],
            'category' => [Rule::requiredIf(! $account), Rule::in(array_keys(ChartOfAccounts::CATEGORIES))],
            'parent' => ['nullable', 'string', 'size:16'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ], ['code.regex' => 'Use letters, numbers, dots and dashes only.']);

        $data['parent_id'] = null;
        if (! $account && ! empty($data['parent'])) {
            $parent = LedgerAccount::query()->where('public_id', $data['parent'])->where('category', $data['category'])->where('is_postable', false)->first()
                ?? throw ValidationException::withMessages(['parent' => 'Choose a group of the same type.']);
            $data['parent_id'] = $parent->id;
        }
        unset($data['parent']);

        return $data + ['is_postable' => true];
    }

    private function form(LedgerAccount $account, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('accounting.accounts._form', [
            'account' => $account,
            'groups' => LedgerAccount::query()->where('is_postable', false)->orderBy('code')->get()->mapWithKeys(fn (LedgerAccount $a) => [$a->public_id => $a->label()]),
        ])->render()]);
    }

    private function audit(Request $request, LedgerAccount $account, string $event, array $old = []): void
    {
        activity('accounting')->event($event)->causedBy($request->user())->performedOn($account)
            ->withProperties(['name' => $account->label(), 'old' => $old, 'attributes' => $account->only(['code', 'name', 'is_active'])])->log($event);
    }
}
