<?php

namespace App\Modules\Accounting\Services;

use App\Models\Contractor;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\Investor;
use App\Models\LedgerAccount;
use App\Models\MoneyAccount;
use App\Models\ProjectCostAllocation;
use App\Models\Supplier;
use App\Modules\Investors\Services\InvestorLedger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** The operational records set against the ledger accounts that should hold the same amount. */
class Reconciliation
{
    /**
     * @return array<int, array{label: string, records: string, ledger: string, difference: string, accounts: array<int, LedgerAccount>}>
     */
    public function checks(): array
    {
        $net = Balances::byAccount();
        $account = fn (string $key) => LedgerAccount::forKey($key);
        $balance = fn (string $key) => Balances::natural($account($key), $net[$account($key)->id] ?? '0');
        $sum = fn (iterable $values) => Money::add('0', ...collect($values)->map(fn ($v) => Money::amount($v))->all());

        $moneyIds = MoneyAccount::query()->pluck('ledger_account_id')->push($account('cheques_in_hand')->id);
        $fromCustomers = Money::amount(Balances::lines()->whereNotNull('journal_lines.customer_id')->whereIn('journal_lines.account_id', $moneyIds)
            ->selectRaw('COALESCE(SUM(journal_lines.debit) - SUM(journal_lines.credit), 0) AS net')->value('net'));

        $rows = [
            ['Suppliers we owe', $sum(Supplier::all()->map(fn (Supplier $s) => $s->balance())), $balance('supplier_payable'), ['supplier_payable']],
            ['Contractors we owe, less advances', $sum(Contractor::all()->map(fn (Contractor $c) => $c->balance())), Money::sub($balance('contractor_payable'), $balance('contractor_advance')), ['contractor_payable', 'contractor_advance']],
            ['Material stock value', Money::amount(DB::table('stock_balances')->sum('owned_value')), $balance('stock'), ['stock']],
            ['Project cost', Money::amount(ProjectCostAllocation::query()->sum('amount')),
                Money::sub(Money::add($balance('wip'), $balance('cost_of_sales')), $balance('cost_to_complete')), ['wip', 'cost_of_sales', 'cost_to_complete']],
            ['Money received from customers', Money::sub(Money::amount(CustomerPayment::query()->where('status', 'posted')->sum('amount')), Money::amount(CustomerRefund::query()->where('status', 'paid')->sum('net_amount'))),
                $fromCustomers, ['customer_receivable', 'customer_advance']],
            ['Investor capital', Money::amount(DB::table('investor_capital_transactions')->where('status', 'posted')
                ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'contribution' THEN amount ELSE -amount END), 0) AS v")->value('v')), $balance('investor_capital'), ['investor_capital']],
            ['Profit owed to investors', $sum(Investor::all()->map(fn (Investor $i) => InvestorLedger::balances($i)['profit_due'])), $balance('investor_profit_payable'), ['investor_profit_payable']],
        ];

        return array_map(fn (array $r) => [
            'label' => $r[0], 'records' => Money::amount($r[1]), 'ledger' => Money::amount($r[2]),
            'difference' => Money::amount(Money::sub($r[1], $r[2])), 'accounts' => array_map($account, $r[3]),
        ], $rows);
    }
}
