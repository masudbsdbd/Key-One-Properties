<?php

namespace App\Modules\Accounting\Rules;

use App\Models\Cheque;
use App\Models\ContractorBill;
use App\Models\ContractorPayment;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialEvent;
use App\Models\FlatProfitCalculation;
use App\Models\FlatSale;
use App\Models\GoodsReceipt;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorPayout;
use App\Models\LedgerAccount;
use App\Models\MaterialTransfer;
use App\Models\MoneyAccount;
use App\Models\PurchaseReturn;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Modules\Accounting\Services\Balances;
use App\Modules\Accounting\Support\MoneyAccounts;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns a financial event into journal lines. One method per event type; an event that moves no
 * money and recognises nothing returns null. Rules read the source document for the exact split.
 */
class PostingRules
{
    /** Events that never post: commitments and steps already covered by another event. */
    private const SILENT = ['booking.created', 'booking.cancelled', 'sale.confirmed', 'installment.due', 'cost.shared_allocated', 'profit.distribution_approved', 'material.borrowed', 'material.returned', 'expense.general', 'expense.project'];

    /**
     * @return array{narration: string, lines: array<int, array<string, mixed>>}|null
     */
    public function for(FinancialEvent $event): ?array
    {
        if (in_array($event->event_type, self::SILENT, true)) {
            return null;
        }
        $method = lcfirst(str_replace(' ', '', ucwords(str_replace(['.', '_'], ' ', $event->event_type))));
        if (! method_exists($this, $method)) {
            throw new RuntimeException("No posting rule for [{$event->event_type}].");
        }
        $source = $event->source ?? throw new RuntimeException('The source document no longer exists.');
        $posting = $this->{$method}($event, $source);
        if ($posting === null) {
            return null;
        }
        $posting['lines'] = array_values(array_filter($posting['lines'], fn (array $l) => Money::isPositive($l['debit'] ?? 0) || Money::isPositive($l['credit'] ?? 0)));

        return $posting['lines'] === [] ? null : $posting;
    }

    // Purchasing and stock

    private function purchaseReceived(FinancialEvent $e, GoodsReceipt $grn): array
    {
        return ['narration' => 'Goods received '.$grn->grn_no.' · '.$grn->supplier->name, 'lines' => [
            $this->dr('stock', $e->amount, ['project_id' => $e->project_id]),
            $this->cr('grni', $e->amount, ['supplier_id' => $grn->supplier_id, 'project_id' => $e->project_id]),
        ]];
    }

    private function purchaseReturned(FinancialEvent $e, PurchaseReturn $return): array
    {
        // `credited` on the return is a flag; the amount taken off the supplier's bill is in the event.
        $credited = Money::amount($e->payload['credited'] ?? 0);
        $dims = ['supplier_id' => $return->supplier_id, 'project_id' => $e->project_id];
        // Stock leaves at its average cost, which can differ from the price the supplier takes the goods back at.
        $stock = Money::amount(StockMovement::query()->where('reference_type', $return->getMorphClass())->where('reference_id', $return->id)->sum('total_value'));
        $stock = Money::isPositive($stock) ? $stock : (string) $e->amount;
        $difference = Money::sub((string) $e->amount, $stock);

        return ['narration' => 'Goods returned '.$return->return_no.' · '.$return->supplier->name, 'lines' => [
            $this->dr('supplier_payable', $credited, $dims),
            $this->dr('grni', Money::sub($e->amount, $credited), $dims),
            $this->cr('stock', $stock, ['project_id' => $e->project_id]),
            Money::cmp($difference, 0) >= 0 ? $this->cr('purchase_variance', $difference, $dims) : $this->dr('purchase_variance', Money::neg($difference), $dims),
        ]];
    }

    private function supplierBillApproved(FinancialEvent $e, SupplierBill $bill): array
    {
        $bill->loadMissing(['lines.receiptLine', 'supplier', 'purchaseOrder']);
        $dims = ['supplier_id' => $bill->supplier_id, 'project_id' => $e->project_id];
        $received = '0';
        foreach ($bill->lines as $line) {
            if ($line->receiptLine) {
                $received = Money::add($received, Money::mul((string) $line->quantity, (string) $line->receiptLine->unit_cost));
            }
        }
        // Stock and project cost stay at the received value, as in the stock and cost records. What the bill
        // adds on top (price differences, carriage, tax) or takes off (discount) goes to one expense account.
        $difference = Money::sub((string) $bill->total_amount, $received);
        $lines = [
            $this->dr('grni', $received, $dims),
            Money::cmp($difference, 0) >= 0 ? $this->dr('purchase_variance', $difference, $dims) : $this->cr('purchase_variance', Money::neg($difference), $dims),
            $this->cr('supplier_payable', (string) $bill->total_amount, $dims),
        ];

        return ['narration' => 'Supplier bill '.$bill->bill_no.' · '.$bill->supplier->name, 'lines' => $lines];
    }

    private function purchaseSupplierPayment(FinancialEvent $e, SupplierPayment $payment): array
    {
        $dims = ['supplier_id' => $payment->supplier_id];

        return ['narration' => 'Paid supplier '.$payment->supplier->name.' · '.$payment->payment_no, 'lines' => [
            $this->dr('supplier_payable', (string) $payment->amount, $dims),
            $this->money($payment, $payment->payment_method, 'credit', Money::sub((string) $payment->amount, (string) $payment->tax_deducted), $dims),
            $this->cr('tax_payable', (string) $payment->tax_deducted, $dims),
        ]];
    }

    private function materialReceived(FinancialEvent $e, StockReceipt $receipt): array
    {
        return ['narration' => 'Stock received '.$receipt->receipt_no, 'lines' => [
            $this->dr('stock', $e->amount, ['project_id' => $e->project_id]),
            $this->cr($receipt->source === 'opening' ? 'opening_equity' : 'stock_adjustment', $e->amount, ['project_id' => $e->project_id]),
        ]];
    }

    private function materialConsumed(FinancialEvent $e, Model $issue): array
    {
        return ['narration' => 'Materials issued '.$issue->issue_no, 'lines' => [
            $this->dr('wip', $e->amount, ['project_id' => $e->project_id]),
            $this->cr('stock', $e->amount, ['project_id' => $issue->warehouse?->project_id]),
        ]];
    }

    private function materialSiteReturned(FinancialEvent $e, Model $return): array
    {
        return ['narration' => 'Materials returned from site '.$return->return_no, 'lines' => [
            $this->dr('stock', $e->amount, ['project_id' => $return->issue?->warehouse?->project_id]),
            $this->cr('wip', $e->amount, ['project_id' => $e->project_id]),
        ]];
    }

    private function materialTransferred(FinancialEvent $e, MaterialTransfer $transfer): ?array
    {
        if ($transfer->source_project_id === $transfer->destination_project_id) {
            return null;
        }

        return ['narration' => 'Material transfer '.$transfer->transfer_no, 'lines' => [
            $this->dr('stock', $e->amount, ['project_id' => $transfer->destination_project_id]),
            $this->cr('stock', $e->amount, ['project_id' => $transfer->source_project_id]),
        ]];
    }

    private function stockAdjusted(FinancialEvent $e, Model $adjustment): array
    {
        $gain = Money::cmp($e->amount, 0) >= 0;
        $amount = $gain ? (string) $e->amount : Money::neg((string) $e->amount);
        $dims = ['project_id' => $e->project_id];

        return ['narration' => 'Stock adjustment '.$adjustment->adjustment_no, 'lines' => $gain
            ? [$this->dr('stock', $amount, $dims), $this->cr('stock_adjustment', $amount, $dims)]
            : [$this->dr('stock_adjustment', $amount, $dims), $this->cr('stock', $amount, $dims)]];
    }

    // Construction, contractors and expenses

    private function labourApproved(FinancialEvent $e, Model $entry): array
    {
        return ['narration' => 'Labour '.$entry->entry_no, 'lines' => [
            $this->dr('wip', $e->amount, ['project_id' => $e->project_id]),
            $this->cr('wages_payable', $e->amount, ['project_id' => $e->project_id]),
        ]];
    }

    private function contractorBillApproved(FinancialEvent $e, ContractorBill $bill): array
    {
        $dims = ['contractor_id' => $bill->contractor_id, 'project_id' => $bill->project_id];

        return ['narration' => 'Contractor bill '.$bill->bill_no.' · '.$bill->contractor->name, 'lines' => [
            $this->dr('wip', $bill->costAmount(), $dims),
            $this->cr('contractor_payable', $bill->chargeAmount(), $dims),
            $this->cr('retention_payable', (string) $bill->retention_amount, $dims),
            // The part of the bill settled from an advance already paid.
            $this->dr('contractor_payable', (string) $bill->advance_adjustment, $dims),
            $this->cr('contractor_advance', (string) $bill->advance_adjustment, $dims),
        ]];
    }

    private function contractorRetentionReleased(FinancialEvent $e, ContractorBill $bill): array
    {
        $dims = ['contractor_id' => $bill->contractor_id, 'project_id' => $bill->project_id];

        return ['narration' => 'Retention released '.$bill->bill_no.' · '.$bill->contractor->name, 'lines' => [
            $this->dr('retention_payable', (string) $bill->gross_amount, $dims),
            $this->cr('contractor_payable', (string) $bill->gross_amount, $dims),
        ]];
    }

    private function contractorPayment(FinancialEvent $e, ContractorPayment $payment): array
    {
        $dims = ['contractor_id' => $payment->contractor_id, 'project_id' => $payment->project_id];

        return ['narration' => ($payment->isAdvance() ? 'Advance to ' : 'Paid contractor ').$payment->contractor->name.' · '.$payment->payment_no, 'lines' => [
            $this->dr($payment->isAdvance() ? 'contractor_advance' : 'contractor_payable', (string) $payment->amount, $dims),
            $this->money($payment, $payment->payment_method, 'credit', Money::sub((string) $payment->amount, (string) $payment->tax_deducted), $dims),
            $this->cr('tax_payable', (string) $payment->tax_deducted, $dims),
        ]];
    }

    private function contractorAdvanceRefunded(FinancialEvent $e, ContractorPayment $payment): array
    {
        $dims = ['contractor_id' => $payment->contractor_id, 'project_id' => $payment->project_id];

        return ['narration' => 'Advance returned by '.$payment->contractor->name.' · '.$payment->payment_no, 'lines' => [
            $this->money($payment, $payment->payment_method, 'debit', (string) $payment->amount, $dims),
            $this->cr('contractor_advance', (string) $payment->amount, $dims),
        ]];
    }

    private function expenseApproved(FinancialEvent $e, Expense $expense): array
    {
        $dims = ['project_id' => $expense->project_id, 'flat_id' => $expense->flat_id];
        $debit = $expense->scope === 'project' && $expense->project_id
            ? $this->dr('wip', (string) $expense->amount, $dims)
            : ['account' => $this->categoryAccount($expense->category), 'debit' => (string) $expense->amount];

        return ['narration' => 'Expense '.$expense->expense_no.' · '.$expense->category->name, 'lines' => [
            $debit + ['description' => $expense->description],
            $this->cr('expense_payable', (string) $expense->amount, $dims),
        ]];
    }

    private function expensePaid(FinancialEvent $e, Expense $expense): array
    {
        return ['narration' => 'Expense paid '.$expense->expense_no.($expense->payee_name ? ' · '.$expense->payee_name : ''), 'lines' => [
            $this->dr('expense_payable', (string) $expense->amount, ['project_id' => $expense->project_id]),
            $this->money($expense, $expense->payment_method, 'credit', (string) $expense->amount, ['project_id' => $expense->project_id]),
        ]];
    }

    // Customers

    private function collectionReceived(FinancialEvent $e, CustomerPayment $payment): array
    {
        $flatId = $e->flat_id ?? $payment->sale?->flat_id ?? $payment->booking?->flat_id;
        $dims = ['customer_id' => $payment->customer_id, 'project_id' => $payment->project_id, 'flat_id' => $flatId];
        $cheque = Cheque::query()->where('source_type', $payment->getMorphClass())->where('source_id', $payment->id)->first();
        $debit = $cheque && $cheque->status !== 'cleared'
            ? $this->dr('cheques_in_hand', (string) $payment->amount, $dims + ['description' => 'Cheque '.$cheque->cheque_no])
            : $this->money($payment, $payment->method, 'debit', (string) $payment->amount, $dims, $cheque?->moneyAccount);

        return ['narration' => 'Received from '.$payment->customer->name.' · '.$payment->payment_no, 'lines' => [
            $debit,
            $this->cr($flatId && $this->isRecognised($flatId) ? 'customer_receivable' : 'customer_advance', (string) $payment->amount, $dims),
        ]];
    }

    private function collectionRefunded(FinancialEvent $e, CustomerRefund $refund): array
    {
        $flatId = $e->flat_id ?? $refund->sale?->flat_id ?? $refund->booking?->flat_id;
        $dims = ['customer_id' => $refund->customer_id, 'project_id' => $refund->project_id, 'flat_id' => $flatId];

        return ['narration' => 'Refund to '.$refund->customer->name.' · '.$refund->refund_no, 'lines' => [
            $this->dr('customer_advance', (string) $refund->amount, $dims),
            $this->money($refund, $refund->payment_method, 'credit', $refund->payable(), $dims),
            $this->cr('cancellation_income', (string) $refund->deduction_amount, $dims),
        ]];
    }

    private function lateFeeAssessed(FinancialEvent $e, Model $assessment): array
    {
        $dims = ['customer_id' => $e->customer_id, 'project_id' => $e->project_id, 'flat_id' => $e->flat_id];

        return ['narration' => 'Late fee · installment '.($e->payload['installment'] ?? ''), 'lines' => [
            $this->dr('customer_receivable', (string) $e->amount, $dims),
            $this->cr('late_fee_income', (string) $e->amount, $dims),
        ]];
    }

    private function lateFeeWaived(FinancialEvent $e, Model $assessment): array
    {
        $amount = Money::neg((string) $e->amount);
        $dims = ['customer_id' => $e->customer_id, 'project_id' => $e->project_id, 'flat_id' => $e->flat_id];

        return ['narration' => 'Late fee waived', 'lines' => [
            $this->dr('late_fee_income', $amount, $dims),
            $this->cr('customer_receivable', $amount, $dims),
        ]];
    }

    // Investors

    private function investmentReceived(FinancialEvent $e, InvestorCapitalTransaction $tx): array
    {
        $dims = ['investor_id' => $tx->investor_id, 'project_id' => $tx->project_id, 'flat_id' => $tx->flat_id];

        return ['narration' => 'Capital received from '.$tx->investor->name.' · '.$tx->transaction_no, 'lines' => [
            $this->money($tx, $tx->payment_method, 'debit', (string) $tx->amount, $dims),
            $this->cr('investor_capital', (string) $tx->amount, $dims),
        ]];
    }

    private function investmentWithdrawn(FinancialEvent $e, InvestorCapitalTransaction $tx): array
    {
        $dims = ['investor_id' => $tx->investor_id, 'project_id' => $tx->project_id, 'flat_id' => $tx->flat_id];

        return ['narration' => 'Capital returned to '.$tx->investor->name.' · '.$tx->transaction_no, 'lines' => [
            $this->dr('investor_capital', (string) $tx->amount, $dims),
            $this->money($tx, $tx->payment_method, 'credit', (string) $tx->amount, $dims),
        ]];
    }

    private function profitDistributionPaid(FinancialEvent $e, InvestorPayout $payout): array
    {
        $dims = ['investor_id' => $payout->investor_id, 'project_id' => $e->project_id, 'flat_id' => $e->flat_id];

        return ['narration' => 'Profit paid to '.$payout->investor->name.' · '.$payout->payout_no, 'lines' => [
            $this->dr('investor_profit_payable', (string) $payout->amount, $dims),
            $this->money($payout, $payout->payment_method, 'credit', (string) $payout->amount, $dims),
        ]];
    }

    // Revenue recognition

    /**
     * Bring the ledger to what the approved calculation says for the unit: revenue, cost of the unit and the
     * investors' share. Only the change against what was recognised before is posted.
     */
    private function profitPayableApproved(FinancialEvent $e, FlatProfitCalculation $calc): ?array
    {
        $calc->loadMissing(['sale.customer', 'allocations', 'flat']);
        $lateFees = Money::amount($calc->basis_snapshot['late_fees'] ?? 0);
        $revenue = Money::sub((string) $calc->revenue_amount, $lateFees); // late fees are income when charged
        $actual = Money::add((string) $calc->direct_cost_amount, (string) $calc->shared_cost_amount, (string) $calc->other_approved_cost_amount);
        $estimate = Money::amount($calc->estimated_cost_amount ?? 0);

        $posted = $this->recognised($calc->flat_id);
        $advance = Money::add(Balances::natural(LedgerAccount::forKey('customer_advance'), $this->netOf('customer_advance', ['flat_id' => $calc->flat_id])), Money::amount($posted[LedgerAccount::forKey('customer_advance')->id] ?? 0));
        $investors = $calc->allocations->groupBy('investor_id')->map(fn (Collection $rows) => Money::add('0', ...$rows->map(fn ($a) => (string) $a->profit_amount)->all()));

        $targets = [
            'sales' => Money::neg($revenue),
            'customer_advance' => $advance,
            'customer_receivable' => Money::sub($revenue, $advance),
            'cost_of_sales' => Money::add($actual, $estimate),
            'wip' => Money::neg($actual),
            'cost_to_complete' => Money::neg($estimate),
        ];
        $lines = $this->differences($targets, $posted, ['project_id' => $calc->project_id, 'flat_id' => $calc->flat_id, 'customer_id' => $calc->sale?->customer_id]);

        // Investors' share: one pair of lines per investor, again only the change.
        $postedByInvestor = $this->recognisedByInvestor($calc->flat_id);
        foreach ($investors->keys()->merge($postedByInvestor->keys())->unique() as $investorId) {
            $change = Money::sub($investors[$investorId] ?? '0', $postedByInvestor[$investorId] ?? '0');
            $dims = ['project_id' => $calc->project_id, 'flat_id' => $calc->flat_id, 'investor_id' => $investorId];
            if (Money::cmp($change, 0) > 0) {
                array_push($lines, $this->dr('investor_profit_expense', $change, $dims), $this->cr('investor_profit_payable', $change, $dims));
            } elseif (Money::cmp($change, 0) < 0) {
                array_push($lines, $this->dr('investor_profit_payable', Money::neg($change), $dims), $this->cr('investor_profit_expense', Money::neg($change), $dims));
            }
        }

        return ['narration' => 'Sale recognised · Unit '.$calc->flat->flat_no.' · '.$calc->calculation_no, 'lines' => $lines];
    }

    /** An approved sale was cancelled: take back whatever was recognised for the unit. */
    private function saleCancelled(FinancialEvent $e, FlatSale $sale): ?array
    {
        $posted = $this->recognised($sale->flat_id);
        $dims = ['project_id' => $sale->project_id, 'flat_id' => $sale->flat_id, 'customer_id' => $sale->customer_id];
        $lines = $this->differences(array_fill_keys(['sales', 'customer_advance', 'customer_receivable', 'cost_of_sales', 'wip', 'cost_to_complete'], '0'), $posted, $dims);
        foreach ($this->recognisedByInvestor($sale->flat_id) as $investorId => $amount) {
            if (Money::isPositive($amount)) {
                array_push($lines, $this->dr('investor_profit_payable', $amount, ['investor_id' => $investorId] + $dims), $this->cr('investor_profit_expense', $amount, ['investor_id' => $investorId] + $dims));
            }
        }
        // Money collected after recognition sat against the receivable; it is the customer's advance again.
        $receivable = Money::sub($this->netOf('customer_receivable', ['flat_id' => $sale->flat_id, 'customer_id' => $sale->customer_id]),
            Money::amount($posted[LedgerAccount::forKey('customer_receivable')->id] ?? 0));
        if (Money::cmp($receivable, 0) < 0) {
            array_push($lines, $this->dr('customer_receivable', Money::neg($receivable), $dims), $this->cr('customer_advance', Money::neg($receivable), $dims));
        }

        return ['narration' => 'Sale cancelled · '.$sale->sale_no, 'lines' => $lines];
    }

    // Helpers

    private function dr(string $key, mixed $amount, array $dims = []): array
    {
        return ['account' => $key, 'debit' => Money::amount($amount)] + $dims;
    }

    private function cr(string $key, mixed $amount, array $dims = []): array
    {
        return ['account' => $key, 'credit' => Money::amount($amount)] + $dims;
    }

    /** The cash or bank side of a payment or receipt. */
    private function money(Model $document, ?string $method, string $side, string $amount, array $dims = [], ?MoneyAccount $override = null): array
    {
        $account = $override ?? MoneyAccounts::for($document->moneyAccount, $method)
            ?? throw new RuntimeException('Choose the cash or bank account of this '.strtolower(class_basename($document)).' first.');

        return ['account' => $account->ledgerAccount, $side => Money::amount($amount)] + $dims;
    }

    /** General expenses post to one account per expense category, created under General Expenses on first use. */
    private function categoryAccount(ExpenseCategory $category): LedgerAccount
    {
        if ($category->ledger_account_id && ($account = LedgerAccount::query()->find($category->ledger_account_id))) {
            return $account;
        }

        return DB::transaction(function () use ($category) {
            $parent = LedgerAccount::query()->where('code', '6000')->lockForUpdate()->firstOrFail();
            $code = (int) $parent->code;
            do {
                $code++;
            } while (LedgerAccount::query()->where('code', (string) $code)->exists());
            $account = new LedgerAccount(['code' => (string) $code, 'name' => $category->name, 'category' => 'expense', 'is_postable' => true, 'is_active' => true]);
            $account->parent_id = $parent->id;
            $account->is_system = true;
            $account->save();
            ExpenseCategory::query()->whereKey($category->id)->update(['ledger_account_id' => $account->id]);

            return $account;
        });
    }

    private function isRecognised(int $flatId): bool
    {
        return Money::cmp($this->netOf('sales', ['flat_id' => $flatId]), 0) < 0;
    }

    /** Net debit of an account for the given dimensions. */
    private function netOf(string $key, array $where): string
    {
        return Money::amount(Balances::lines(where: $where)->where('journal_lines.account_id', LedgerAccount::forKey($key)->id)
            ->selectRaw('COALESCE(SUM(journal_lines.debit) - SUM(journal_lines.credit), 0) AS net')->value('net'));
    }

    /**
     * What recognition journals hold for a unit so far.
     *
     * @return Collection<int, string> account id => net debit
     */
    private function recognised(int $flatId): Collection
    {
        return $this->recognitionLines($flatId)->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, SUM(journal_lines.debit) - SUM(journal_lines.credit) AS net')->pluck('net', 'account_id')->map(fn ($v) => Money::amount($v));
    }

    /** @return Collection<int, string> investor id => profit share recognised as payable */
    private function recognisedByInvestor(int $flatId): Collection
    {
        return $this->recognitionLines($flatId)->where('journal_lines.account_id', LedgerAccount::forKey('investor_profit_payable')->id)->whereNotNull('journal_lines.investor_id')
            ->groupBy('journal_lines.investor_id')->selectRaw('journal_lines.investor_id, SUM(journal_lines.credit) - SUM(journal_lines.debit) AS net')
            ->pluck('net', 'investor_id')->map(fn ($v) => Money::amount($v));
    }

    private function recognitionLines(int $flatId)
    {
        return Balances::lines(where: ['flat_id' => $flatId])
            ->whereIn('journal_entries.source_type', [(new FlatProfitCalculation)->getMorphClass(), (new FlatSale)->getMorphClass()]);
    }

    /**
     * Lines that move each account from its recognised amount to the target (net debit; negative is credit).
     *
     * @param  array<string, string>  $targets  system key => net debit wanted
     */
    private function differences(array $targets, Collection $posted, array $dims): array
    {
        $lines = [];
        foreach ($targets as $key => $target) {
            $change = Money::sub($target, Money::amount($posted[LedgerAccount::forKey($key)->id] ?? 0));
            if (Money::cmp($change, 0) > 0) {
                $lines[] = $this->dr($key, $change, $dims);
            } elseif (Money::cmp($change, 0) < 0) {
                $lines[] = $this->cr($key, Money::neg($change), $dims);
            }
        }

        return $lines;
    }
}
