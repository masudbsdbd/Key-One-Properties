<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Models\Contractor;
use App\Models\Customer;
use App\Models\Flat;
use App\Models\Investor;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\MoneyAccount;
use App\Models\Project;
use App\Models\Supplier;
use App\Modules\Accounting\Services\Balances;
use App\Modules\Accounting\Services\EventPoster;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Services\Statements;
use App\Modules\Accounting\Support\AccountingSettings;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\Money;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private Statements $statements) {}

    public function overview(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('accounting.dashboard.view'), 403);
        $year = Statements::range($request, 'this-year');
        $month = Statements::range(new Request(['period' => 'this-month']));
        $net = Balances::byAccount();
        $natural = fn (string $key) => Balances::natural(LedgerAccount::forKey($key), $net[LedgerAccount::forKey($key)->id] ?? '0');
        $pl = $this->statements->profitAndLoss($year['from'], $year['to']);
        $plMonth = $this->statements->profitAndLoss($month['from'], $month['to']);

        return view('accounting.reports.overview', [
            'enabled' => AccountingSettings::enabled(),
            'year' => $year,
            'cash' => Money::add('0', ...MoneyAccount::query()->pluck('ledger_account_id')->map(fn ($id) => Money::amount($net[$id] ?? 0))->all()),
            'receivable' => $natural('customer_receivable'),
            'payable' => $this->payableSections(Ledger::today()->toDateString())['trade'],
            'pl' => $pl, 'plMonth' => $plMonth,
            'sheet' => $this->statements->balanceSheet(min($year['to'], Ledger::today()->toDateString())),
            'attention' => array_filter([
                'negative' => $user->can('accounting.cashbank.view') ? MoneyAccount::query()->get()->filter(fn (MoneyAccount $a) => Money::cmp($net[$a->ledger_account_id] ?? 0, 0) < 0)->count() : 0,
                'backlog' => $user->can('accounting.reconciliation.view') && AccountingSettings::enabled() ? EventPoster::backlog()->count() : 0,
                'journals' => $user->can('accounting.journal.approve') ? JournalEntry::query()->where('status', 'submitted')->count() : 0,
                'cheques' => $user->canAny(['accounting.cashbank.view', 'accounting.cheque.manage']) ? Cheque::query()->whereIn('status', Cheque::OPEN)->count() : 0,
            ]),
        ]);
    }

    public function trialBalance(Request $request): View|JsonResponse|Response
    {
        abort_unless($request->user()->can('accounting.reports.view'), 403);
        $range = Statements::range($request, 'all');
        $project = $this->project($request);
        $data = $this->statements->trialBalance($range['to'], ['project_id' => $project?->id]);

        if ($request->filled('format')) {
            $this->exportable($request);

            return Report::make('Trial Balance')->meta(['As of' => $range['to'], 'Project' => $project?->name])
                ->columns(['Code' => 'text', 'Account' => 'text', 'Debit' => 'money', 'Credit' => 'money'])
                ->rows(fn () => $data['rows']->map(fn ($r) => [$r['account']->code, $r['account']->name, $r['debit'], $r['credit']]))
                ->totals(['Total', null, $data['debit'], $data['credit']])->respond($request);
        }

        return $this->page($request, 'trial-balance', 'Trial Balance', compact('range', 'data', 'project'), ['project' => true, 'asOf' => true]);
    }

    public function profitLoss(Request $request): View|JsonResponse|Response
    {
        abort_unless($request->user()->can('accounting.reports.view'), 403);
        $range = Statements::range($request);
        $project = $this->project($request);
        $where = ['project_id' => $project?->id];
        $data = $this->statements->profitAndLoss($range['from'], $range['to'], $where);
        $before = $request->boolean('compare') ? Statements::previous($range) : null;
        $previous = $before ? $this->statements->profitAndLoss($before['from'], $before['to'], $where) : null;

        if ($request->filled('format')) {
            $this->exportable($request);
            $rows = collect();
            foreach (['income' => 'Income', 'cost_of_sales' => 'Cost of Units Sold', 'expenses' => 'Expenses', 'investors' => "Investors' Share"] as $key => $label) {
                $data[$key]->each(fn ($r) => $rows->push([$label, $r['account']->code, $r['account']->name, $r['amount']]));
            }
            foreach (['gross_profit' => 'Gross Profit', 'profit_before_investors' => 'Profit Before Investors', 'net_profit' => 'Net Profit'] as $key => $label) {
                $rows->push([$label, null, null, $data[$key]]);
            }

            return Report::make('Profit & Loss')->meta(['Period' => $range['label'], 'Project' => $project?->name])
                ->columns(['Section' => 'text', 'Code' => 'text', 'Account' => 'text', 'Amount' => 'money'])->rows(fn () => $rows)->respond($request);
        }

        return $this->page($request, 'profit-loss', 'Profit & Loss', compact('range', 'data', 'project', 'previous', 'before'), ['project' => true, 'compare' => true]);
    }

    public function balanceSheet(Request $request): View|JsonResponse|Response
    {
        abort_unless($request->user()->can('accounting.reports.view'), 403);
        $range = Statements::range($request, 'all');
        $data = $this->statements->balanceSheet($range['to']);

        if ($request->filled('format')) {
            $this->exportable($request);
            $rows = collect();
            foreach (['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $key => $label) {
                $data[$key]->each(fn ($r) => $rows->push([$label, $r['account']->code, $r['account']->name, $r['amount']]));
            }
            $rows->push(['Equity', null, 'Profit Not Yet Closed', $data['earnings']]);
            foreach (['total_assets' => 'Total Assets', 'total_liabilities' => 'Total Liabilities', 'total_equity' => 'Total Equity'] as $key => $label) {
                $rows->push([$label, null, null, $data[$key]]);
            }

            return Report::make('Balance Sheet')->meta(['As of' => $range['to']])
                ->columns(['Section' => 'text', 'Code' => 'text', 'Account' => 'text', 'Amount' => 'money'])->rows(fn () => $rows)->respond($request);
        }

        return $this->page($request, 'balance-sheet', 'Balance Sheet', compact('range', 'data'), ['asOf' => true]);
    }

    public function cashFlow(Request $request): View|JsonResponse|Response
    {
        abort_unless($request->user()->can('accounting.reports.view'), 403);
        $range = Statements::range($request);
        $data = $this->statements->cashFlow($range['from'], $range['to']);

        if ($request->filled('format')) {
            $this->exportable($request);
            $rows = collect([['Opening Cash & Bank', null, $data['opening']]]);
            foreach (['operating' => 'Day-to-Day Business', 'investing' => 'Assets Bought and Sold', 'financing' => 'Owners, Investors and Loans'] as $key => $label) {
                $data['sections'][$key]->each(fn ($r) => $rows->push([$label, $r['label'], $r['amount']]));
            }
            $rows->push(['Change in Cash & Bank', null, $data['change']])->push(['Closing Cash & Bank', null, $data['closing']]);

            return Report::make('Cash Flow')->meta(['Period' => $range['label']])
                ->columns(['Section' => 'text', 'For' => 'text', 'Amount' => 'money'])->rows(fn () => $rows)->respond($request);
        }

        return $this->page($request, 'cash-flow', 'Cash Flow', compact('range', 'data'));
    }

    public function projects(Request $request): View|JsonResponse|Response
    {
        abort_unless($request->user()->can('accounting.reports.view'), 403);
        $range = Statements::range($request, 'all');
        $project = $this->project($request);
        if ($project) {
            $items = Flat::query()->where('project_id', $project->id)->orderBy('flat_no')->get(['id', 'public_id', 'project_id', 'flat_no']);
            $figures = $this->statements->byDimension('flat_id', $items->pluck('id'), $range['from'], $range['to']);
        } else {
            $items = Project::query()->visibleTo($request->user())->orderBy('code')->get(['id', 'public_id', 'code', 'name']);
            $figures = $this->statements->byDimension('project_id', $items->pluck('id'), $range['from'], $range['to']);
        }
        $rows = $items->map(fn ($item) => ['item' => $item, 'label' => $project ? 'Unit '.$item->flat_no : $item->code.' · '.$item->name] + ($figures[$item->id] ?? []))
            ->filter(fn (array $r) => isset($r['revenue']))->values();
        $columns = ['revenue' => 'Revenue', 'cost_of_sales' => 'Cost of Units Sold', 'investor_profit_expense' => "Investors' Share", 'profit' => 'Profit',
            'wip' => 'Work in Progress', 'customer_receivable' => 'Customers Owe', 'customer_advance' => 'Customer Advances'];
        $totals = collect($columns)->map(fn ($l, $k) => Money::add('0', ...$rows->pluck($k)->all()));

        if ($request->filled('format')) {
            $this->exportable($request);

            return Report::make($project ? 'Unit Financials' : 'Project Financials')->meta(['Period' => $range['label'], 'Project' => $project?->name])
                ->columns([$project ? 'Unit' : 'Project' => 'text'] + array_fill_keys(array_values($columns), 'money'))
                ->rows(fn () => $rows->map(fn ($r) => [$r['label'], ...array_map(fn ($k) => $r[$k], array_keys($columns))]))
                ->totals(['Total', ...$totals->values()->all()])->respond($request);
        }

        return $this->page($request, 'projects', $project ? 'Unit Financials' : 'Project Financials', compact('range', 'rows', 'columns', 'totals', 'project'), ['project' => true]);
    }

    public function receivables(Request $request): View|JsonResponse|Response
    {
        abort_unless($request->user()->can('accounting.reports.view'), 403);
        $range = Statements::range($request, 'all');
        $figures = $this->statements->byParty('customer_id', ['customer_receivable', 'customer_advance'], $range['to']);
        $customers = Customer::query()->whereIn('id', $figures->keys())->get(['id', 'public_id', 'full_name', 'customer_code', 'created_by'])->keyBy('id');
        $rows = $figures->map(fn (array $row, int $id) => [
            'party' => $customers[$id] ?? null, 'name' => $customers[$id]->full_name ?? 'No customer named', 'code' => $customers[$id]->customer_code ?? null,
            'owes' => $row['customer_receivable'], 'advance' => $row['customer_advance'], 'net' => Money::amount(Money::sub($row['customer_receivable'], $row['customer_advance'])),
        ])->sortByDesc(fn (array $r) => (float) $r['net'])->values();
        $totals = collect(['owes', 'advance', 'net'])->mapWithKeys(fn ($k) => [$k => Money::add('0', ...$rows->pluck($k)->all())]);

        if ($request->filled('format')) {
            $this->exportable($request);

            return Report::make('Receivables')->meta(['As of' => $range['to']])
                ->columns(['Customer' => 'text', 'Code' => 'text', 'Owes Us' => 'money', 'Advance We Hold' => 'money', 'Net' => 'money'])
                ->rows(fn () => $rows->map(fn ($r) => [$r['name'], $r['code'], $r['owes'], $r['advance'], $r['net']]))
                ->totals(['Total', null, $totals['owes'], $totals['advance'], $totals['net']])->respond($request);
        }

        return $this->page($request, 'receivables', 'Receivables', compact('range', 'rows', 'totals'), ['asOf' => true]);
    }

    public function payables(Request $request): View|JsonResponse|Response
    {
        abort_unless($request->user()->can('accounting.reports.view'), 403);
        $range = Statements::range($request, 'all');
        ['sections' => $sections, 'trade' => $trade, 'total' => $total] = $this->payableSections($range['to']);

        if ($request->filled('format')) {
            $this->exportable($request);
            $rows = collect();
            foreach ($sections as [$label, $list]) {
                $list->each(fn ($r) => $rows->push([$label, $r['name'], $r['amount']]));
            }

            return Report::make('Payables')->meta(['As of' => $range['to']])->columns(['Group' => 'text', 'Name' => 'text', 'We Owe' => 'money'])
                ->rows(fn () => $rows)->totals(['Total', null, $total])->respond($request);
        }

        return $this->page($request, 'payables', 'Payables', compact('range', 'sections', 'trade', 'total'), ['asOf' => true]);
    }

    /**
     * What the company owes on a date: suppliers, contractors and other dues first, then investors.
     *
     * @return array{sections: array<string, array{0: string, 1: Collection, 2: string}>, trade: string, total: string}
     */
    private function payableSections(string $to): array
    {
        $sum = fn (Collection $rows, string $key) => Money::add('0', ...$rows->pluck($key)->all());

        $supplierFigures = $this->statements->byParty('supplier_id', ['supplier_payable'], $to);
        $suppliers = Supplier::query()->whereIn('id', $supplierFigures->keys())->get()->keyBy('id');
        $supplierRows = $supplierFigures->map(fn (array $row, int $id) => ['party' => $suppliers[$id] ?? null, 'name' => $suppliers[$id]->name ?? 'No supplier named', 'amount' => $row['supplier_payable']])
            ->sortByDesc(fn ($r) => (float) $r['amount'])->values();

        $contractorFigures = $this->statements->byParty('contractor_id', ['contractor_payable', 'retention_payable', 'contractor_advance'], $to);
        $contractors = Contractor::query()->whereIn('id', $contractorFigures->keys())->get()->keyBy('id');
        $contractorRows = $contractorFigures->map(fn (array $row, int $id) => [
            'party' => $contractors[$id] ?? null, 'name' => $contractors[$id]->name ?? 'No contractor named',
            'payable' => $row['contractor_payable'], 'retention' => $row['retention_payable'], 'advance' => $row['contractor_advance'],
            'amount' => Money::amount(Money::sub(Money::add($row['contractor_payable'], $row['retention_payable']), $row['contractor_advance'])),
        ])->sortByDesc(fn ($r) => (float) $r['amount'])->values();

        $investorFigures = $this->statements->byParty('investor_id', ['investor_capital', 'investor_profit_payable'], $to);
        $investors = Investor::query()->whereIn('id', $investorFigures->keys())->get()->keyBy('id');
        $investorRows = $investorFigures->map(fn (array $row, int $id) => [
            'party' => $investors[$id] ?? null, 'name' => $investors[$id]->name ?? 'No investor named',
            'capital' => $row['investor_capital'], 'profit' => $row['investor_profit_payable'], 'amount' => Money::amount(Money::add($row['investor_capital'], $row['investor_profit_payable'])),
        ])->sortByDesc(fn ($r) => (float) $r['amount'])->values();

        $net = Balances::byAccount(null, $to);
        $otherRows = collect(['expense_payable', 'wages_payable', 'tax_payable', 'loan_payable', 'grni'])->map(fn (string $key) => [
            'account' => LedgerAccount::forKey($key), 'name' => LedgerAccount::forKey($key)->name, 'amount' => Balances::natural(LedgerAccount::forKey($key), $net[LedgerAccount::forKey($key)->id] ?? '0'),
        ])->filter(fn ($r) => Money::cmp($r['amount'], 0) !== 0)->values();

        $sections = [
            'suppliers' => ['Suppliers', $supplierRows, $sum($supplierRows, 'amount')],
            'contractors' => ['Contractors', $contractorRows, $sum($contractorRows, 'amount')],
            'other' => ['Other', $otherRows, $sum($otherRows, 'amount')],
            'investors' => ['Investors', $investorRows, $sum($investorRows, 'amount')],
        ];
        $trade = Money::amount(Money::add($sections['suppliers'][2], $sections['contractors'][2], $sections['other'][2]));

        return ['sections' => $sections, 'trade' => $trade, 'total' => Money::amount(Money::add($trade, $sections['investors'][2]))];
    }

    public function ledger(Request $request): View|JsonResponse|Response
    {
        abort_unless($request->user()->can('accounting.ledger.view'), 403);
        $range = Statements::range($request);
        $project = $this->project($request);
        $account = LedgerAccount::query()->where('is_postable', true)->where('public_id', (string) $request->input('account'))->first();
        $where = ['project_id' => $project?->id];
        $data = null;
        if ($account) {
            $opening = $this->statements->openingBalance($account, $range['from'], $where);
            $query = $this->statements->ledgerLines($account, $range['from'], $range['to'], $where)->orderBy('journal_entries.accounting_date')->orderBy('journal_lines.id');
            if ($request->filled('format')) {
                $this->exportable($request);

                return Report::make('General Ledger · '.$account->label())->meta(['Period' => $range['label'], 'Project' => $project?->name, 'Opening balance' => $opening])
                    ->columns(['Date' => 'date', 'Journal' => 'text', 'Details' => 'text', 'Debit' => 'money', 'Credit' => 'money'])->sumMoney()
                    ->rows(fn () => $query->with('entry')->lazy()->map(fn ($l) => [$l->entry->accounting_date, $l->entry->journal_no, $l->description ?: $l->entry->narration, $l->debit, $l->credit]))->respond($request);
            }
            $lines = TableQuery::paginate($query->with(['entry.source', 'project', 'flat', 'customer', 'supplier', 'contractor', 'investor']), $request);
            // Balance carried into the page: opening plus everything on the earlier pages.
            $before = $lines->isEmpty() ? '0' : Money::amount((clone $query)->reorder()
                ->where(fn ($q) => $q->where('journal_entries.accounting_date', '<', $lines->first()->accounting_date)
                    ->orWhere(fn ($q) => $q->where('journal_entries.accounting_date', $lines->first()->accounting_date)->where('journal_lines.id', '<', $lines->first()->id)))
                ->select(DB::raw('COALESCE(SUM(journal_lines.debit) - SUM(journal_lines.credit), 0) AS net'))->value('net'));
            $running = Money::add($opening, Balances::natural($account, $before));
            foreach ($lines as $line) {
                $running = Money::add($running, Balances::natural($account, Money::sub((string) $line->debit, (string) $line->credit)));
                $line->setAttribute('balance', Money::amount($running));
            }
            $totals = (clone $query)->reorder()->select(DB::raw('COALESCE(SUM(journal_lines.debit), 0) AS debit, COALESCE(SUM(journal_lines.credit), 0) AS credit'))->toBase()->first();
            $data = ['opening' => $opening, 'lines' => $lines, 'debit' => Money::amount($totals->debit), 'credit' => Money::amount($totals->credit),
                'closing' => Money::amount(Money::add($opening, Balances::natural($account, Money::sub((string) $totals->debit, (string) $totals->credit))))];
        }

        return $this->page($request, 'ledger', 'General Ledger', compact('range', 'data', 'account', 'project'), ['project' => true, 'account' => true]);
    }

    private function project(Request $request): ?Project
    {
        return $request->filled('project') ? Project::query()->visibleTo($request->user())->where('public_id', (string) $request->input('project'))->first() : null;
    }

    private function exportable(Request $request): void
    {
        abort_unless($request->user()->can('accounting.reports.export'), 403);
    }

    /** A statement page; filter changes fetch only the body. */
    private function page(Request $request, string $report, string $title, array $data, array $filters = []): View|JsonResponse
    {
        $data += ['report' => $report, 'title' => $title, 'filters' => $filters, 'exportUrl' => $request->fullUrlWithQuery(['partial' => null, 'page' => null]),
            'canExport' => $request->user()->can('accounting.reports.export')];
        if ($request->boolean('partial')) {
            return Ajax::success(null, ['html' => view('accounting.reports._body', $data)->render()]);
        }

        return view('accounting.reports.show', $data + ['projects' => ($filters['project'] ?? false) ? Project::query()->visibleTo($request->user())->orderBy('code')->get(['id', 'public_id', 'code', 'name']) : collect()]);
    }
}
