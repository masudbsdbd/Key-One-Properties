<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Models\Flat;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\MoneyAccount;
use App\Models\Project;
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
            'payable' => Money::add($natural('supplier_payable'), $natural('contractor_payable'), $natural('expense_payable'), $natural('wages_payable')),
            'pl' => $pl, 'plMonth' => $plMonth,
            'sheet' => $this->statements->balanceSheet(min($year['to'], Ledger::today()->toDateString())),
            'attention' => array_filter([
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
