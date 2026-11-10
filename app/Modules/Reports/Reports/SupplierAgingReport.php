<?php

namespace App\Modules\Reports\Reports;

use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Dates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SupplierAgingReport extends BaseReport
{
    public function key(): string
    {
        return 'supplier-aging';
    }

    public function name(): string
    {
        return 'Supplier Payable Aging';
    }

    public function group(): string
    {
        return 'purchase';
    }

    public function basis(): string
    {
        return 'Approved bills less approved payments';
    }

    public function filters(): array
    {
        return ['supplier'];
    }

    public function search(): array
    {
        return ['bill_no', 'supplier_invoice_no'];
    }

    public function columns(): array
    {
        return ['Supplier' => 'text', 'Bill' => 'text', 'Invoice Date' => 'date', 'Due Date' => 'date', 'Amount' => 'money', 'Outstanding' => 'money', 'Days Overdue' => 'number', 'Bucket' => 'text'];
    }

    public function query(Request $request, User $user): Builder
    {
        $paid = '(SELECT COALESCE(SUM(a.amount),0) FROM supplier_payment_allocations a JOIN supplier_payments p ON p.id = a.payment_id WHERE a.supplier_bill_id = supplier_bills.id AND p.status = ?)';
        $query = SupplierBill::query()->visibleTo($user)->whereIn('status', ['approved', 'paid'])->whereRaw("total_amount > {$paid}", ['approved'])
            ->with('supplier:id,name')->selectRaw("supplier_bills.*, total_amount - {$paid} AS outstanding_amount", ['approved']);
        if ($s = Supplier::findByPublicId($request->input('supplier'))) {
            $query->where('supplier_id', $s->id);
        }

        return $query->orderByRaw('COALESCE(due_date, invoice_date)');
    }

    public function row(mixed $b): array
    {
        $due = $b->due_date ?? $b->invoice_date;
        $days = max(0, (int) $due->diffInDays(Dates::today(), false));

        return [$b->supplier->name, $b->bill_no, $b->invoice_date, $b->due_date, $b->total_amount, $b->outstanding_amount, $days, AgingBuckets::label($days)];
    }
}
