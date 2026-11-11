<?php

namespace App\Modules\Accounting\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The seeded chart of accounts. Posting rules refer to accounts by `system_key`, so an accountant
 * may rename or re-point an account without touching the rules.
 */
class ChartOfAccounts
{
    public const CATEGORIES = ['asset' => 'Assets', 'liability' => 'Liabilities', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expenses'];

    /** Debit-normal categories; the rest are credit-normal. */
    public const DEBIT_NORMAL = ['asset', 'expense'];

    /** Parent accounts that hold one child per money account. */
    public const MONEY_PARENTS = ['cash' => '1010', 'bank' => '1020', 'mobile' => '1030'];

    /**
     * code => [name, category, parent code, system key, postable]
     */
    public const ACCOUNTS = [
        '1000' => ['Cash & Bank', 'asset', null, null, false],
        '1010' => ['Cash', 'asset', '1000', null, false],
        '1020' => ['Bank Accounts', 'asset', '1000', null, false],
        '1030' => ['Mobile Banking', 'asset', '1000', null, false],
        '1100' => ['Cheques in Hand', 'asset', null, 'cheques_in_hand', true],
        '1200' => ['Customer Receivables', 'asset', null, 'customer_receivable', true],
        '1300' => ['Material Stock', 'asset', null, 'stock', true],
        '1400' => ['Construction Work in Progress', 'asset', null, 'wip', true],
        '1500' => ['Advances to Contractors', 'asset', null, 'contractor_advance', true],
        '1700' => ['Fixed Assets', 'asset', null, 'fixed_assets', true],
        '2000' => ['Supplier Payables', 'liability', null, 'supplier_payable', true],
        '2010' => ['Goods Received Not Billed', 'liability', null, 'grni', true],
        '2100' => ['Contractor Payables', 'liability', null, 'contractor_payable', true],
        '2110' => ['Contractor Retention Payable', 'liability', null, 'retention_payable', true],
        '2150' => ['Wages Payable', 'liability', null, 'wages_payable', true],
        '2200' => ['Customer Advances', 'liability', null, 'customer_advance', true],
        '2300' => ['Investor Capital', 'liability', null, 'investor_capital', true],
        '2310' => ['Investor Profit Payable', 'liability', null, 'investor_profit_payable', true],
        '2400' => ['Tax Payable', 'liability', null, 'tax_payable', true],
        '2500' => ['Expenses Payable', 'liability', null, 'expense_payable', true],
        '2600' => ['Loans Payable', 'liability', null, 'loan_payable', true],
        '2800' => ['Estimated Cost to Complete', 'liability', null, 'cost_to_complete', true],
        '3000' => ["Owner's Capital", 'equity', null, 'owner_capital', true],
        '3100' => ["Owner's Drawings", 'equity', null, 'owner_drawings', true],
        '3200' => ['Retained Earnings', 'equity', null, 'retained_earnings', true],
        '3900' => ['Opening Balance Equity', 'equity', null, 'opening_equity', true],
        '4000' => ['Property Sales', 'income', null, 'sales', true],
        '4100' => ['Late Fee Income', 'income', null, 'late_fee_income', true],
        '4200' => ['Cancellation Deductions', 'income', null, 'cancellation_income', true],
        '4900' => ['Other Income', 'income', null, 'other_income', true],
        '5000' => ['Cost of Units Sold', 'expense', null, 'cost_of_sales', true],
        '5100' => ['Investor Profit Share', 'expense', null, 'investor_profit_expense', true],
        '6000' => ['General Expenses', 'expense', null, 'general_expense', false],
        '6090' => ['Other General Expenses', 'expense', '6000', 'general_expense_other', true],
        '6100' => ['Bank Charges', 'expense', null, 'bank_charges', true],
        '6200' => ['Stock Adjustments', 'expense', null, 'stock_adjustment', true],
        '6300' => ['Purchase Price Differences', 'expense', null, 'purchase_variance', true],
    ];

    /** Insert accounts that are missing. Safe to run again; existing rows are left as they are. */
    public static function install(): void
    {
        $existing = DB::table('ledger_accounts')->pluck('id', 'code');
        foreach (self::ACCOUNTS as $code => [$name, $category, $parent, $key, $postable]) {
            if (isset($existing[$code])) {
                continue;
            }
            $existing[$code] = DB::table('ledger_accounts')->insertGetId([
                'public_id' => Str::lower(Str::random(16)),
                'parent_id' => $parent ? $existing[$parent] : null,
                'code' => $code,
                'name' => $name,
                'category' => $category,
                'is_postable' => $postable,
                'is_system' => true,
                'system_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
