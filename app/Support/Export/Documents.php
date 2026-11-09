<?php

namespace App\Support\Export;

use App\Models\ContractorBill;
use App\Models\ContractorPayment;
use App\Models\ContractorWorkOrder;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorFlatAgreement;
use App\Models\InvestorPayout;
use App\Models\MaterialBorrow;
use App\Models\MaterialTransfer;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Modules\Investors\Controllers\AgreementController;
use App\Modules\Investors\Controllers\CapitalController;
use App\Modules\Investors\Services\InvestorService;
use App\Support\Format;
use App\Support\Money;
use App\Support\Phone;

/**
 * Printable layouts of the operational documents. Authorization happens in the controllers.
 */
class Documents
{
    private static function supplierBox($s): array
    {
        return [$s->name, $s->supplier_code, $s->contact_person, $s->mobile ? Phone::display($s->mobile) : null, $s->address, $s->tax_id ? 'TIN/BIN '.$s->tax_id : null];
    }

    private static function contractorBox($c): array
    {
        return [$c->name, $c->contractor_code.($c->trade ? ' · '.$c->trade : ''), $c->contact_person, $c->mobile ? Phone::display($c->mobile) : null, $c->address];
    }

    public static function purchaseOrder(PurchaseOrder $po): Document
    {
        $po->loadMissing(['supplier', 'warehouse', 'project', 'lines.material.unit', 'creator', 'approver']);

        return Document::make('Purchase Order', $po->po_no)->stamp(PurchaseOrder::STATUSES[$po->status])
            ->parties(['Supplier' => self::supplierBox($po->supplier), 'Deliver To' => [$po->warehouse->name, $po->project?->name ?? 'Company', $po->warehouse->address]])
            ->meta(['Order Date' => $po->order_date, 'Expected' => $po->expected_date, 'Prepared By' => $po->creator->name, 'Approved By' => $po->approver?->name])
            ->lines(['Material' => 'text', 'Unit' => 'text', 'Quantity' => 'qty', 'Unit Price' => 'money', 'Discount' => 'money', 'Tax' => 'money', 'Amount' => 'money'],
                $po->lines->map(fn ($l) => [$l->material->name, $l->material->unit->code, $l->quantity, $l->unit_price, (float) $l->discount_amount ? $l->discount_amount : null, (float) $l->tax_amount ? $l->tax_amount : null, $l->line_total]))
            ->summary(['Subtotal' => $po->subtotal, 'Discount' => (float) $po->discount_amount ? '-'.$po->discount_amount : null, 'Tax' => (float) $po->tax_amount ? $po->tax_amount : null, 'Total' => $po->total_amount])
            ->notes($po->notes);
    }

    public static function goodsReceipt(GoodsReceipt $grn): Document
    {
        $grn->loadMissing(['supplier', 'warehouse', 'purchaseOrder', 'lines.material.unit', 'receiver']);

        return Document::make('Goods Receipt Note', $grn->grn_no)
            ->parties(['Supplier' => self::supplierBox($grn->supplier), 'Received At' => [$grn->warehouse->name, $grn->warehouse->code]])
            ->meta(['Date' => $grn->received_at, 'Purchase Order' => $grn->purchaseOrder->po_no, 'Supplier Challan' => $grn->supplier_challan_no, 'Received By' => $grn->receiver->name])
            ->lines(['Material' => 'text', 'Unit' => 'text', 'Received' => 'qty', 'Accepted' => 'qty', 'Rejected' => 'qty'],
                $grn->lines->map(fn ($l) => [$l->material->name, $l->material->unit->code, $l->received_qty, $l->accepted_qty, (float) $l->rejected_qty ? $l->rejected_qty : null]))
            ->notes($grn->notes)
            ->signatures(['Received by', 'Store keeper', 'Approved by']);
    }

    public static function purchaseReturn(PurchaseReturn $return): Document
    {
        $return->loadMissing(['supplier', 'receipt', 'lines.material.unit']);

        return Document::make('Purchase Return', $return->return_no)
            ->parties(['Supplier' => self::supplierBox($return->supplier)])
            ->meta(['Date' => $return->posted_at, 'Goods Receipt' => $return->receipt->grn_no, 'Reason' => $return->reason])
            ->lines(['Material' => 'text', 'Unit' => 'text', 'Quantity' => 'qty', 'Unit Cost' => 'money', 'Amount' => 'money'],
                $return->lines->map(fn ($l) => [$l->material->name, $l->material->unit->code, $l->quantity, $l->unit_cost, $l->amount]))
            ->summary(['Total' => $return->total_amount])
            ->signatures(['Returned by', 'Received by (supplier)', 'Approved by']);
    }

    public static function supplierBill(SupplierBill $bill): Document
    {
        $bill->loadMissing(['supplier', 'purchaseOrder', 'lines', 'creator']);

        return Document::make('Supplier Bill', $bill->bill_no)->stamp(SupplierBill::STATUSES[$bill->status])
            ->parties(['Supplier' => self::supplierBox($bill->supplier)])
            ->meta(['Invoice No' => $bill->supplier_invoice_no, 'Invoice Date' => $bill->invoice_date, 'Due Date' => $bill->due_date, 'Purchase Order' => $bill->purchaseOrder?->po_no])
            ->lines(['Description' => 'text', 'Quantity' => 'qty', 'Unit Price' => 'money', 'Amount' => 'money'],
                $bill->lines->map(fn ($l) => [$l->description, $l->quantity, $l->unit_price, $l->amount]))
            ->summary(['Subtotal' => $bill->subtotal, 'VAT / Tax' => (float) $bill->tax_amount ? $bill->tax_amount : null, 'Paid' => in_array($bill->status, ['approved', 'paid'], true) ? $bill->paidAmount() : null, 'Total' => $bill->total_amount])
            ->notes($bill->notes);
    }

    public static function supplierPayment(SupplierPayment $payment): Document
    {
        $payment->loadMissing(['supplier', 'allocations.bill', 'creator']);

        return Document::make('Payment Voucher', $payment->payment_no)->stamp(SupplierPayment::STATUSES[$payment->status])
            ->parties(['Paid To' => self::supplierBox($payment->supplier)])
            ->meta(['Date' => $payment->payment_date, 'Method' => SupplierPayment::METHODS[$payment->payment_method], 'Reference' => $payment->payment_reference, 'Prepared By' => $payment->creator->name])
            ->lines(['Bill' => 'text', 'Invoice Date' => 'text', 'Allocated' => 'money'],
                $payment->allocations->map(fn ($a) => [$a->bill->bill_no, $a->bill->invoice_date, $a->amount]))
            ->summary(['Advance' => Money::isPositive($adv = Money::sub((string) $payment->amount, Money::add('0', ...$payment->allocations->map(fn ($a) => (string) $a->amount)->all()))) ? $adv : null, 'Amount Paid' => $payment->amount])
            ->notes($payment->notes)
            ->signatures(['Prepared by', 'Approved by', 'Received by']);
    }

    public static function workOrder(ContractorWorkOrder $wo): Document
    {
        $wo->loadMissing(['contractor', 'project', 'workPackage', 'creator', 'approver']);

        return Document::make('Work Order', $wo->wo_no)->stamp(ContractorWorkOrder::STATUSES[$wo->status])
            ->parties(['Contractor' => self::contractorBox($wo->contractor), 'Project' => [$wo->project->name, $wo->project->code, $wo->workPackage?->label()]])
            ->meta(['Title' => $wo->title, 'Start' => $wo->start_date, 'End' => $wo->end_date, 'Retention' => rtrim(rtrim($wo->retention_percent, '0'), '.').'%', 'Prepared By' => $wo->creator->name, 'Approved By' => $wo->approver?->name])
            ->summary(['Contract Amount' => $wo->contract_amount])
            ->notes($wo->scope)
            ->signatures(['Contractor', 'Project engineer', 'Approved by']);
    }

    public static function contractorBill(ContractorBill $bill): Document
    {
        $bill->loadMissing(['contractor', 'workOrder', 'project', 'measurementLinks.measurement', 'creator']);
        $release = $bill->kind === 'retention_release';

        return Document::make($release ? 'Retention Release' : 'Contractor Running Bill', $bill->bill_no)->stamp(ContractorBill::STATUSES[$bill->status])
            ->parties(['Contractor' => self::contractorBox($bill->contractor), 'Work Order' => [$bill->workOrder->wo_no, $bill->workOrder->title, $bill->project->name]])
            ->meta(['Bill Date' => $bill->bill_date, 'Prepared By' => $bill->creator->name])
            ->lines($release ? [] : ['Work Done' => 'text', 'Date' => 'text', 'Quantity' => 'text', 'Rate' => 'money', 'Amount' => 'money'],
                $release ? [] : $bill->measurementLinks->map(fn ($l) => [$l->measurement->description, $l->measurement->measurement_date, Format::qty($l->measurement->quantity).' '.$l->measurement->unit, $l->measurement->rate, $l->amount]))
            ->summary($release ? ['Retention Released' => $bill->gross_amount] : [
                'Gross' => $bill->gross_amount,
                'Advance Adjustment' => (float) $bill->advance_adjustment ? '-'.$bill->advance_adjustment : null,
                'Retention' => (float) $bill->retention_amount ? '-'.$bill->retention_amount : null,
                'Other Deductions'.($bill->deduction_note ? ' ('.$bill->deduction_note.')' : '') => (float) $bill->other_deductions ? '-'.$bill->other_deductions : null,
                'Net Payable' => $bill->net_payable,
            ])
            ->notes($bill->notes)
            ->signatures(['Contractor', 'Checked by', 'Approved by']);
    }

    public static function contractorPayment(ContractorPayment $payment): Document
    {
        $payment->loadMissing(['contractor', 'workOrder', 'bill', 'project', 'creator']);

        return Document::make($payment->bill_id ? 'Payment Voucher' : 'Advance Voucher', $payment->payment_no)->stamp(ContractorPayment::STATUSES[$payment->status])
            ->parties(['Paid To' => self::contractorBox($payment->contractor), 'Against' => [$payment->bill?->bill_no ?? 'Advance', $payment->workOrder->wo_no.' · '.$payment->workOrder->title, $payment->project->name]])
            ->meta(['Date' => $payment->payment_date, 'Method' => ContractorPayment::METHODS[$payment->payment_method], 'Reference' => $payment->reference, 'Prepared By' => $payment->creator->name])
            ->summary(['Amount Paid' => $payment->amount])
            ->notes($payment->notes)
            ->signatures(['Prepared by', 'Approved by', 'Received by']);
    }

    public static function expense(Expense $expense): Document
    {
        $expense->loadMissing(['project', 'workPackage', 'category.parent', 'creator']);

        return Document::make('Expense Voucher', $expense->expense_no)->stamp(Expense::STATUSES[$expense->status])
            ->parties(['Paid To' => [$expense->payee_name ?: '—'], 'Charged To' => [$expense->scope === 'project' ? $expense->project->name : 'General (company)', $expense->workPackage?->label()]])
            ->meta(['Date' => $expense->expense_date, 'Category' => $expense->category->label(), 'Paid On' => $expense->paid_on,
                'Method' => $expense->payment_method ? Expense::METHODS[$expense->payment_method] : null, 'Reference' => $expense->payment_reference, 'Prepared By' => $expense->creator->name])
            ->lines(['Description' => 'text', 'Amount' => 'money'], [[$expense->description, $expense->amount]])
            ->summary(['Total' => $expense->amount])
            ->signatures(['Prepared by', 'Approved by', 'Received by']);
    }

    public static function transfer(MaterialTransfer $t, bool $showValue): Document
    {
        $t->loadMissing(['lines.material.unit', 'sourceWarehouse.project', 'destinationWarehouse.project', 'borrow', 'requester']);
        $columns = ['Material' => 'text', 'Unit' => 'text', 'Quantity' => 'qty'] + ($showValue ? ['Unit Cost' => 'money', 'Value' => 'money'] : []);

        return Document::make('Material Transfer Note', $t->transfer_no)->stamp(MaterialTransfer::STATUSES[$t->status])
            ->parties(['From' => [$t->sourceWarehouse->name, $t->sourceWarehouse->project?->name ?? 'Company'], 'To' => [$t->destinationWarehouse->name, $t->destinationWarehouse->project?->name ?? 'Company']])
            ->meta(['Type' => MaterialTransfer::KINDS[$t->kind], 'Borrow' => $t->borrow?->borrow_no, 'Requested By' => $t->requester->name, 'Posted' => $t->posted_at, 'Reason' => $t->reason])
            ->lines($columns, $t->lines->map(fn ($l) => array_merge([$l->material->name, $l->material->unit->code, $l->quantity], $showValue ? [$l->unit_cost, $l->total_value] : [])))
            ->signatures(['Issued by', 'Received by', 'Approved by']);
    }

    public static function borrow(MaterialBorrow $b): Document
    {
        $b->loadMissing(['lines.material.unit', 'sourceWarehouse', 'destinationWarehouse', 'sourceProject', 'destinationProject', 'requester']);

        return Document::make('Material Borrow Note', $b->borrow_no)->stamp(MaterialBorrow::STATUSES[$b->status] ?? $b->status)
            ->parties(['Lender (owner)' => [$b->sourceProject->name, $b->sourceWarehouse->name], 'Borrower (custody)' => [$b->destinationProject->name, $b->destinationWarehouse->name]])
            ->meta(['Requested By' => $b->requester->name, 'Requested' => $b->created_at, 'Issued' => $b->issued_at])
            ->lines(['Material' => 'text', 'Unit' => 'text', 'Approved' => 'qty', 'Issued' => 'qty', 'Returned' => 'qty', 'Converted' => 'qty', 'Outstanding' => 'qty'],
                $b->lines->map(fn ($l) => [$l->material->name, $l->material->unit->code, $l->approved_qty, $l->issued_qty, $l->returned_qty, $l->converted_qty, $l->outstanding()]))
            ->notes($b->notes)
            ->signatures(['Issued by', 'Received by', 'Approved by']);
    }

    public static function investorAgreement(InvestorFlatAgreement $agreement): Document
    {
        $agreement->loadMissing(['investor', 'flat.building', 'project', 'creator', 'approver']);
        $i = $agreement->investor;

        return Document::make('Investment Agreement', $agreement->agreement_no)->stamp(InvestorFlatAgreement::STATUSES[$agreement->status])
            ->parties(['Investor' => [$i->name, $i->investor_code, Phone::display($i->mobile), $i->address], 'Unit' => [$agreement->project->name, $agreement->flat->building->name.' · Unit '.$agreement->flat->flat_no, number_format((float) $agreement->flat->saleable_area).' '.$agreement->flat->area_unit]])
            ->meta(['Version' => (string) $agreement->version, 'Effective From' => $agreement->effective_from, 'Effective To' => $agreement->effective_to ?? 'Open', 'Prepared By' => $agreement->creator->name, 'Approved By' => $agreement->approver?->name])
            ->lines(['Term' => 'text', 'Value' => 'text'], [['Profit share', AgreementController::pct($agreement->profit_share_percent)], ['Capital contributed to date', Format::money(InvestorService::capitalBalance($agreement->investor_id, $agreement->flat_id))]])
            ->summary(['Committed Capital' => $agreement->committed_capital])
            ->notes($agreement->terms)
            ->signatures(['Investor', 'Prepared by', 'Authorised signatory']);
    }

    public static function capitalTransaction(InvestorCapitalTransaction $transaction): Document
    {
        $transaction->loadMissing(['investor', 'agreement', 'flat.building', 'project', 'creator', 'approver']);
        $i = $transaction->investor;
        $in = $transaction->transaction_type === 'contribution';

        return Document::make($in ? 'Capital Receipt' : 'Capital Return Voucher', $transaction->transaction_no)
            ->stamp($transaction->status === 'posted' ? null : InvestorCapitalTransaction::STATUSES[$transaction->status])
            ->parties([($in ? 'Received From' : 'Paid To') => [$i->name, $i->investor_code, Phone::display($i->mobile)], 'Investment' => [$transaction->project->name, 'Unit '.$transaction->flat->flat_no, $transaction->agreement->agreement_no]])
            ->meta(['Date' => $transaction->transaction_date, 'Method' => CapitalController::METHODS[$transaction->payment_method] ?? null, 'Reference' => $transaction->reference_no, 'Prepared By' => $transaction->creator->name, 'Posted By' => $transaction->approver?->name])
            ->summary([($in ? 'Amount Received' : 'Amount Paid') => $transaction->amount])
            ->notes($transaction->notes)
            ->signatures([$in ? 'Investor' : 'Received by', 'Prepared by', 'Authorised signatory']);
    }

    public static function investorPayout(InvestorPayout $payout): Document
    {
        $payout->loadMissing(['distribution.flat.project', 'investor', 'poster']);
        $i = $payout->investor;
        $d = $payout->distribution;

        return Document::make('Profit Payout Voucher', $payout->payout_no)->stamp($payout->status === 'reversed' ? 'REVERSED' : null)
            ->parties(['Paid To' => [$i->name, $i->investor_code, Phone::display($i->mobile)], 'For' => [$d->flat->project->name, 'Unit '.$d->flat->flat_no, $d->distribution_no]])
            ->meta(['Date' => $payout->paid_at, 'Method' => CapitalController::METHODS[$payout->payment_method] ?? null, 'Reference' => $payout->payment_reference, 'Posted By' => $payout->poster->name])
            ->summary(['Amount Paid' => $payout->amount])
            ->signatures(['Received by', 'Prepared by', 'Authorised signatory']);
    }

    public static function moneyReceipt(CustomerPayment $payment): Document
    {
        $payment->loadMissing(['customer', 'project', 'booking.flat', 'sale.flat', 'allocations.item', 'receipt', 'receiver']);
        $c = $payment->customer;
        $unit = ($payment->sale ?? $payment->booking)->flat->flat_no;

        return Document::make('Money Receipt', $payment->receipt->receipt_no)->stamp($payment->receipt->voided_at ? 'VOID' : null)
            ->parties(['Received From' => [$c->full_name, $c->customer_code, Phone::display($c->primary_mobile)], 'For' => [$payment->project->name, 'Unit '.$unit, $payment->sale?->sale_no ?? $payment->booking?->booking_no]])
            ->meta(['Payment No' => $payment->payment_no, 'Date' => $payment->payment_date, 'Method' => CustomerPayment::METHODS[$payment->method] ?? $payment->method, 'Reference' => $payment->reference_no, 'Received By' => $payment->receiver->name])
            ->lines(['Applied To' => 'text', 'Amount' => 'money'], $payment->allocations->whereNull('reversal_of_id')->filter(fn ($a) => Money::isPositive($a->amount))->map(fn ($a) => [
                match ($a->allocation_type) {
                    'booking' => 'Booking money', 'unapplied_advance' => 'Advance', 'late_fee' => 'Late fee · installment #'.$a->item?->sequence_no, default => 'Installment #'.$a->item?->sequence_no.' · due '.Format::date($a->item?->due_date)
                },
                $a->amount]))
            ->summary(['Amount Received' => $payment->amount])
            ->signatures(['Customer', 'Received by', 'Authorised signatory']);
    }
}
