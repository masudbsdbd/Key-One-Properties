<?php

namespace App\Support\Reconciliation;

use App\Models\ContractorBill;
use App\Models\ContractorLedgerEntry;
use App\Models\ContractorPayment;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\CustomerStatementEntry;
use App\Models\Expense;
use App\Models\FlatSale;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorLedgerEntry;
use App\Models\InvestorPayout;
use App\Models\InvestorProfitAllocation;
use App\Models\LabourEntry;
use App\Models\LateFeeAssessment;
use App\Models\ProjectCostAllocation;
use App\Models\PurchaseReturn;
use App\Models\StockIssue;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Rebuilds what each posted document should have written to the supplier ledger, the contractor ledger
 * and project cost, and compares it with what was actually written. Read-only.
 */
class SubledgerReconciler
{
    /**
     * @return array<int, array{check: string, document: string, expected: string, actual: string}>
     */
    public function differences(): array
    {
        return [
            ...$this->supplierLedger(),
            ...$this->contractorLedger(),
            ...$this->projectCost(),
            ...$this->customerStatement(),
            ...$this->investorLedger(),
        ];
    }

    private function supplierLedger(): array
    {
        $actual = $this->net(SupplierLedgerEntry::query());
        $expected = collect();
        Supplier::query()->withTrashed()->where('opening_balance', '!=', 0)->each(fn ($s) => $expected->put($this->key($s), Money::amount($s->opening_balance)));
        SupplierBill::query()->whereIn('status', ['approved', 'paid'])->each(fn ($b) => $expected->put($this->key($b), Money::amount($b->total_amount)));
        SupplierPayment::query()->where('status', 'approved')->each(fn ($p) => $expected->put($this->key($p), Money::neg(Money::amount($p->amount))));
        PurchaseReturn::query()->where('credited', true)->with('lines')->each(fn ($r) => $expected->put($this->key($r),
            Money::neg(Money::amount(Money::add('0', ...$r->lines->where('was_billed', true)->map(fn ($l) => (string) $l->amount)->all())))));

        return $this->compare('Supplier ledger', $expected, $actual);
    }

    private function contractorLedger(): array
    {
        $actual = $this->net(ContractorLedgerEntry::query());
        $expected = collect();
        ContractorBill::query()->whereIn('status', ['approved', 'paid'])->each(fn ($b) => $expected->put($this->key($b), $b->chargeAmount()));
        ContractorPayment::query()->where('status', 'approved')->each(fn ($p) => $expected->put($this->key($p), Money::neg(Money::amount($p->amount))));

        return $this->compare('Contractor ledger', $expected, $actual);
    }

    private function projectCost(): array
    {
        $actual = ProjectCostAllocation::query()->selectRaw('source_type, source_id, SUM(amount) AS total')->groupBy('source_type', 'source_id')->get()
            ->mapWithKeys(fn ($r) => [$r->source_type.'#'.$r->source_id => Money::amount((string) $r->total)]);
        $expected = collect();
        StockIssue::query()->where('status', 'posted')->with('lines')->each(fn ($i) => $expected->put($this->key($i),
            Money::amount(Money::add('0', ...$i->lines->map(fn ($l) => (string) $l->total_value)->all()))));
        LabourEntry::query()->where('status', 'approved')->each(fn ($e) => $expected->put($this->key($e), Money::amount($e->total_amount)));
        ContractorBill::query()->whereIn('status', ['approved', 'paid'])->each(fn ($b) => $expected->put($this->key($b), $b->costAmount()));
        Expense::query()->where('scope', 'project')->whereIn('status', ['approved', 'paid'])->each(fn ($e) => $expected->put($this->key($e), Money::amount($e->amount)));

        return $this->compare('Project cost', $expected, $actual);
    }

    /** Customer statement: sale charge, payments, refunds and late fees net per source. */
    private function customerStatement(): array
    {
        $actual = CustomerStatementEntry::query()->selectRaw('source_type, source_id, SUM(charge_amount - credit_amount) AS net')->groupBy('source_type', 'source_id')->get()
            ->mapWithKeys(fn ($r) => [$r->source_type.'#'.$r->source_id => Money::amount((string) $r->net)]);
        $expected = collect();
        FlatSale::query()->whereIn('status', FlatSale::ACTIVE)->each(fn ($s) => $expected->put($this->key($s), Money::amount($s->net_contract_value)));
        CustomerPayment::query()->where('status', 'posted')->each(fn ($p) => $expected->put($this->key($p), Money::neg(Money::amount($p->amount))));
        CustomerRefund::query()->where('status', 'paid')->each(fn ($r) => $expected->put($this->key($r), Money::amount($r->payable())));
        LateFeeAssessment::query()->where('status', 'posted')->each(fn ($a) => $expected->put($this->key($a), Money::amount($a->amount)));

        return $this->compare('Customer statement', $expected, $actual);
    }

    /** Investor ledger: capital in/out, profit payable and paid per source. */
    private function investorLedger(): array
    {
        $kind = 'COALESCE(o.entry_kind, e.entry_kind)';
        $actual = InvestorLedgerEntry::query()->from('investor_ledger_entries as e')->leftJoin('investor_ledger_entries as o', 'o.id', '=', 'e.reversal_of_id')
            ->selectRaw("e.source_type, e.source_id, SUM(CASE WHEN {$kind} IN ('capital_out', 'profit_paid') THEN -e.amount ELSE e.amount END) AS net")
            ->groupBy('e.source_type', 'e.source_id')->get()
            ->mapWithKeys(fn ($r) => [$r->source_type.'#'.$r->source_id => Money::amount((string) $r->net)]);
        $expected = collect();
        InvestorCapitalTransaction::query()->where('status', 'posted')->each(fn ($t) => $expected->put($this->key($t),
            $t->transaction_type === 'contribution' ? Money::amount($t->amount) : Money::neg(Money::amount($t->amount))));
        InvestorProfitAllocation::query()->where('status', 'approved')->each(fn ($a) => $expected->put($this->key($a), Money::amount($a->payable_change)));
        InvestorPayout::query()->where('status', 'posted')->each(fn ($p) => $expected->put($this->key($p), Money::neg(Money::amount($p->amount))));

        return $this->compare('Investor ledger', $expected->reject(fn ($v) => Money::cmp($v, '0') === 0), $actual);
    }

    /** Net (charge − payment) per source document. */
    private function net($query): Collection
    {
        return $query->selectRaw('source_type, source_id, SUM(charge_amount - payment_amount) AS net')->groupBy('source_type', 'source_id')->get()
            ->mapWithKeys(fn ($r) => [$r->source_type.'#'.$r->source_id => Money::amount((string) $r->net)]);
    }

    private function key(Model $model): string
    {
        return $model->getMorphClass().'#'.$model->getKey();
    }

    /**
     * Documents missing from $expected must net to zero (draft, reversed, cancelled).
     */
    private function compare(string $check, Collection $expected, Collection $actual): array
    {
        $diffs = [];
        foreach ($expected->keys()->merge($actual->keys())->unique() as $key) {
            $want = $expected->get($key, '0.00');
            $have = $actual->get($key, '0.00');
            if (Money::cmp($want, $have) !== 0) {
                $diffs[] = ['check' => $check, 'document' => $this->label($key), 'expected' => $want, 'actual' => $have];
            }
        }

        return $diffs;
    }

    private function label(string $key): string
    {
        [$type, $id] = explode('#', $key);
        $model = class_exists($type) ? $type::query()->find($id) : null;
        $number = $model ? ($model->bill_no ?? $model->payment_no ?? $model->sale_no ?? $model->refund_no ?? $model->transaction_no ?? $model->payout_no ?? $model->return_no ?? $model->issue_no ?? $model->expense_no ?? $model->supplier_code ?? $model->entry_no ?? null) : null;

        return class_basename($type).' '.($number ?? '#'.$id);
    }
}
