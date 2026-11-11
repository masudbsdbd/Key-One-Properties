<?php

namespace App\Modules\Accounting\Services;

use App\Models\LedgerAccount;
use App\Models\MoneyAccount;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** The figures behind every financial statement, all taken from posted journal lines. */
class Statements
{
    public const PERIODS = [
        'this-month' => 'This Month', 'last-month' => 'Last Month', 'this-year' => 'This Fiscal Year', 'last-year' => 'Last Fiscal Year', 'all' => 'All Time', 'custom' => 'Custom Dates',
    ];

    /** Cash flow sections by the account on the other side of the money. */
    private const INVESTING = ['fixed_assets'];

    private const FINANCING = ['investor_capital', 'investor_profit_payable', 'owner_capital', 'owner_drawings', 'loan_payable'];

    /** Cash flow lines in plain words; accounts sharing a label are added together. Other accounts keep their name. */
    private const FLOW_LABELS = [
        'customer_receivable' => 'Received from customers', 'customer_advance' => 'Received from customers', 'cheques_in_hand' => 'Received from customers',
        'cancellation_income' => 'Received from customers',
        'supplier_payable' => 'Paid to suppliers', 'grni' => 'Paid to suppliers',
        'contractor_payable' => 'Paid to contractors', 'contractor_advance' => 'Paid to contractors', 'retention_payable' => 'Paid to contractors',
        'expense_payable' => 'Expenses paid', 'wages_payable' => 'Labour wages paid', 'tax_payable' => 'Tax kept back and paid', 'bank_charges' => 'Bank charges',
        'fixed_assets' => 'Assets bought and sold',
        'investor_capital' => 'Investor capital', 'investor_profit_payable' => 'Profit paid to investors',
        'owner_capital' => "Owner's money put in", 'owner_drawings' => "Owner's money taken out", 'loan_payable' => 'Loans',
    ];

    /**
     * The period a report request asks for; fiscal-year presets follow the Accounting Settings.
     *
     * @return array{preset: string, from: ?string, to: string, label: string}
     */
    public static function range(Request $request, string $default = 'this-year'): array
    {
        $today = CarbonImmutable::now(config('app.display_timezone'))->startOfDay();
        $preset = array_key_exists((string) $request->input('period'), self::PERIODS) ? (string) $request->input('period') : $default;
        $year = Periods::yearStart($today);
        [$from, $to] = match ($preset) {
            'this-month' => [$today->startOfMonth(), $today->endOfMonth()],
            'last-month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last-year' => [$year->subYear(), $year->subDay()],
            'all' => [null, $today],
            'custom' => [self::date($request->input('from')), self::date($request->input('to')) ?? $today],
            default => [$year, $year->addYear()->subDay()],
        };
        if ($from && $from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'preset' => $preset, 'from' => $from?->toDateString(), 'to' => $to->toDateString(),
            'label' => ($from ? $from->format('j M Y').' – ' : 'Up to ').$to->format('j M Y'),
        ];
    }

    /** The period of the same length just before the given one. */
    public static function previous(array $range): ?array
    {
        if (! $range['from']) {
            return null;
        }
        $from = CarbonImmutable::parse($range['from']);
        $to = CarbonImmutable::parse($range['to']);
        $whole = $from->day === 1 && $to->isSameDay($to->endOfMonth());
        $months = (int) $from->diffInMonths($to->addDay());
        [$pFrom, $pTo] = $whole && $months >= 1
            ? [$from->subMonthsNoOverflow($months), $from->subDay()]
            : [$from->subDays((int) $from->diffInDays($to) + 1), $from->subDay()];

        return ['preset' => 'custom', 'from' => $pFrom->toDateString(), 'to' => $pTo->toDateString(), 'label' => $pFrom->format('j M Y').' – '.$pTo->format('j M Y')];
    }

    /**
     * Closing balance of every account on a date, on its debit or credit side.
     *
     * @return array{rows: Collection, debit: string, credit: string}
     */
    public function trialBalance(string $to, array $where = []): array
    {
        $net = Balances::byAccount(null, $to, $where);
        $rows = LedgerAccount::query()->whereIn('id', $net->keys())->orderBy('code')->get()
            ->map(fn (LedgerAccount $a) => ['account' => $a, 'debit' => Money::cmp($net[$a->id], 0) > 0 ? $net[$a->id] : '0.00', 'credit' => Money::cmp($net[$a->id], 0) < 0 ? Money::neg($net[$a->id]) : '0.00'])
            ->filter(fn (array $r) => Money::isPositive($r['debit']) || Money::isPositive($r['credit']))->values();

        return ['rows' => $rows, 'debit' => Money::add('0', ...$rows->pluck('debit')->all()), 'credit' => Money::add('0', ...$rows->pluck('credit')->all())];
    }

    /**
     * Income, cost of units sold, other expenses and the investors' share for a period.
     *
     * @return array<string, mixed>
     */
    public function profitAndLoss(?string $from, string $to, array $where = []): array
    {
        $net = Balances::byAccount($from, $to, $where, withClosing: false);
        $accounts = LedgerAccount::query()->whereIn('id', $net->keys())->whereIn('category', ['income', 'expense'])->with('parent')->orderBy('code')->get()
            ->map(fn (LedgerAccount $a) => ['account' => $a, 'amount' => Balances::natural($a, $net[$a->id])])->filter(fn (array $r) => Money::cmp($r['amount'], 0) !== 0);
        $is = fn (array $row, string $key) => $row['account']->system_key === $key || $row['account']->parent?->system_key === $key;
        $sum = fn (Collection $rows) => Money::add('0', ...$rows->pluck('amount')->all());

        $income = $accounts->filter(fn ($r) => $r['account']->category === 'income')->values();
        $costOfSales = $accounts->filter(fn ($r) => $is($r, 'cost_of_sales'))->values();
        $investors = $accounts->filter(fn ($r) => $is($r, 'investor_profit_expense'))->values();
        $expenses = $accounts->filter(fn ($r) => $r['account']->category === 'expense' && ! $is($r, 'cost_of_sales') && ! $is($r, 'investor_profit_expense'))->values();

        $gross = Money::sub($sum($income), $sum($costOfSales));
        $beforeInvestors = Money::sub($gross, $sum($expenses));

        return [
            'income' => $income, 'cost_of_sales' => $costOfSales, 'expenses' => $expenses, 'investors' => $investors,
            'total_income' => $sum($income), 'total_cost_of_sales' => $sum($costOfSales), 'gross_profit' => Money::amount($gross),
            'total_expenses' => $sum($expenses), 'profit_before_investors' => Money::amount($beforeInvestors),
            'total_investors' => $sum($investors), 'net_profit' => Money::amount(Money::sub($beforeInvestors, $sum($investors))),
        ];
    }

    /**
     * What the company owns and owes on a date. Profit not yet moved to Retained Earnings shows as its own line.
     *
     * @return array<string, mixed>
     */
    public function balanceSheet(string $to, array $where = []): array
    {
        $net = Balances::byAccount(null, $to, $where);
        $accounts = LedgerAccount::query()->whereIn('id', $net->keys())->with('parent')->orderBy('code')->get();
        $rows = fn (string $category) => $accounts->where('category', $category)
            ->map(fn (LedgerAccount $a) => ['account' => $a, 'amount' => Balances::natural($a, $net[$a->id])])->filter(fn (array $r) => Money::cmp($r['amount'], 0) !== 0)->values();
        $sum = fn (Collection $rows) => Money::add('0', ...$rows->pluck('amount')->all());

        [$assets, $liabilities, $equity] = [$rows('asset'), $rows('liability'), $rows('equity')];
        $earnings = Money::sub($sum($rows('income')), $sum($rows('expense')));
        $totalEquity = Money::add($sum($equity), $earnings);

        return [
            'assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity, 'earnings' => Money::amount($earnings),
            'total_assets' => $sum($assets), 'total_liabilities' => $sum($liabilities), 'total_equity' => Money::amount($totalEquity),
            'total_liabilities_equity' => Money::amount(Money::add($sum($liabilities), $totalEquity)),
            'difference' => Money::amount(Money::sub($sum($assets), Money::add($sum($liabilities), $totalEquity))),
        ];
    }

    /**
     * Money in and out for a period, grouped by what the money was for (the account on the other side of each
     * journal that touches a cash or bank account). Opening balances entered for accounts count as opening cash.
     *
     * @return array<string, mixed>
     */
    public function cashFlow(?string $from, string $to): array
    {
        $moneyLedgerIds = MoneyAccount::query()->pluck('ledger_account_id');
        $cashOn = function (?string $date, bool $before) use ($moneyLedgerIds): string {
            if ($moneyLedgerIds->isEmpty() || ($before && $date === null)) {
                return '0.00';
            }

            return Money::amount(Balances::lines(null, $before ? CarbonImmutable::parse($date)->subDay()->toDateString() : $date)->whereIn('journal_lines.account_id', $moneyLedgerIds)
                ->selectRaw('COALESCE(SUM(journal_lines.debit) - SUM(journal_lines.credit), 0) AS net')->value('net'));
        };

        $flows = Balances::lines($from, $to)
            ->whereNotIn('journal_lines.account_id', $moneyLedgerIds)
            ->whereIn('journal_lines.journal_entry_id', fn ($q) => $q->select('journal_entry_id')->from('journal_lines')->whereNotNull('money_account_id'))
            ->groupBy('journal_lines.account_id', 'journal_entries.journal_type')
            ->selectRaw('journal_lines.account_id, journal_entries.journal_type, SUM(journal_lines.credit) - SUM(journal_lines.debit) AS amount')->get();
        $accounts = LedgerAccount::query()->whereIn('id', $flows->pluck('account_id'))->get()->keyBy('id');

        $sections = ['operating' => collect(), 'investing' => collect(), 'financing' => collect()];
        $openingEntered = '0';
        foreach ($flows->groupBy('account_id') as $accountId => $group) {
            $account = $accounts[$accountId];
            $opening = Money::add('0', ...$group->where('journal_type', 'opening')->map(fn ($r) => Money::amount($r->amount))->all());
            $openingEntered = Money::add($openingEntered, $opening);
            $amount = Money::sub(Money::add('0', ...$group->map(fn ($r) => Money::amount($r->amount))->all()), $opening);
            if (Money::cmp($amount, 0) === 0) {
                continue;
            }
            $section = in_array($account->system_key, self::INVESTING, true) ? 'investing' : (in_array($account->system_key, self::FINANCING, true) ? 'financing' : 'operating');
            $label = self::FLOW_LABELS[$account->system_key] ?? $account->name;
            $row = $sections[$section]->get($label, ['label' => $label, 'amount' => '0', 'accounts' => collect()]);
            $row['amount'] = Money::amount(Money::add($row['amount'], $amount));
            $row['accounts']->push($account);
            $sections[$section]->put($label, $row);
        }
        $sections = array_map(fn (Collection $rows) => $rows->filter(fn (array $r) => Money::cmp($r['amount'], 0) !== 0)->sortBy(fn (array $r) => $r['accounts']->min('code'))->values(), $sections);
        $totals = collect($sections)->map(fn (Collection $rows) => Money::add('0', ...$rows->pluck('amount')->all()));
        $opening = Money::add($cashOn($from, true), $openingEntered);
        $change = Money::add('0', ...$totals->values()->all());

        return [
            'sections' => collect($sections), 'totals' => $totals,
            'opening' => Money::amount($opening), 'change' => Money::amount($change), 'closing' => Money::amount(Money::add($opening, $change)),
            'book_closing' => $cashOn($to, false),
        ];
    }

    /**
     * One line per project (or per unit of a project): what was earned, what it cost, and what is still open.
     *
     * @param  Collection<int, int>  $projectIds  projects the viewer may see
     */
    public function byDimension(string $dimension, Collection $ids, ?string $from, string $to, array $where = []): Collection
    {
        $column = 'journal_lines.'.$dimension;
        $keys = ['sales', 'late_fee_income', 'cost_of_sales', 'investor_profit_expense', 'wip', 'customer_receivable', 'customer_advance'];
        $accounts = collect($keys)->mapWithKeys(fn ($k) => [LedgerAccount::forKey($k)->id => $k]);
        $period = ['sales', 'late_fee_income', 'cost_of_sales', 'investor_profit_expense'];

        $rows = collect();
        foreach ([[$from, $period], [null, array_values(array_diff($keys, $period))]] as [$start, $wanted]) {
            Balances::lines($start, $to, $where, withClosing: false)->whereIn($column, $ids)
                ->whereIn('journal_lines.account_id', $accounts->filter(fn ($k) => in_array($k, $wanted, true))->keys())
                ->groupBy($column, 'journal_lines.account_id')
                ->selectRaw($column.' AS dim, journal_lines.account_id, SUM(journal_lines.debit) - SUM(journal_lines.credit) AS net')->get()
                ->each(function ($r) use ($rows, $accounts) {
                    $row = $rows->get($r->dim, []);
                    $row[$accounts[$r->account_id]] = Balances::natural(LedgerAccount::forKey($accounts[$r->account_id]), Money::amount($r->net));
                    $rows->put($r->dim, $row);
                });
        }

        return $rows->map(function (array $row) use ($keys) {
            $row += array_fill_keys($keys, '0.00');
            $row['revenue'] = Money::amount(Money::add($row['sales'], $row['late_fee_income']));
            $row['profit'] = Money::amount(Money::sub(Money::sub($row['revenue'], $row['cost_of_sales']), $row['investor_profit_expense']));

            return $row;
        });
    }

    /**
     * Balances of the given accounts split by a party column (customer_id, supplier_id, …) on a date.
     * The key 0 collects lines that name no party.
     *
     * @param  array<int, string>  $keys  system keys
     * @return Collection<int, array<string, string>> party id => system key => balance in the account's own direction
     */
    public function byParty(string $column, array $keys, string $to): Collection
    {
        $accounts = collect($keys)->mapWithKeys(fn ($k) => [LedgerAccount::forKey($k)->id => $k]);
        $rows = collect();
        Balances::lines(null, $to)->whereIn('journal_lines.account_id', $accounts->keys())
            ->groupBy('journal_lines.'.$column, 'journal_lines.account_id')
            ->selectRaw('journal_lines.'.$column.' AS party, journal_lines.account_id, SUM(journal_lines.debit) - SUM(journal_lines.credit) AS net')->get()
            ->each(function ($r) use ($rows, $accounts) {
                $row = $rows->get((int) $r->party, []);
                $row[$accounts[$r->account_id]] = Balances::natural(LedgerAccount::forKey($accounts[$r->account_id]), Money::amount($r->net));
                $rows->put((int) $r->party, $row);
            });

        return $rows->map(fn (array $row) => $row + array_fill_keys($keys, '0.00'))
            ->filter(fn (array $row) => collect($row)->contains(fn ($v) => Money::cmp($v, 0) !== 0));
    }

    /** Lines of one account for the general ledger, oldest first within the period. */
    public function ledgerLines(LedgerAccount $account, ?string $from, string $to, array $where = []): Builder
    {
        return Balances::lines($from, $to, $where)->where('journal_lines.account_id', $account->id)
            ->select('journal_lines.*', 'journal_entries.accounting_date');
    }

    public function openingBalance(LedgerAccount $account, ?string $from, array $where = []): string
    {
        return $from ? Balances::of($account, CarbonImmutable::parse($from)->subDay()->toDateString(), $where) : '0.00';
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        $value = (string) $value;

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? CarbonImmutable::parse($value) : null;
    }
}
