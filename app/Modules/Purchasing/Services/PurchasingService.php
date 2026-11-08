<?php

namespace App\Modules\Purchasing\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedger;
use App\Support\DocumentNumber;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PurchasingService
{
    public function __construct(
        private StockLedger $stock,
        private SupplierLedger $ledger,
        private FinancialEventRecorder $events,
    ) {}

    public function createSupplier(array $data, string $opening, User $user): Supplier
    {
        return DB::transaction(function () use ($data, $opening, $user) {
            $supplier = new Supplier($data);
            $supplier->supplier_code = DocumentNumber::next('SUP');
            $supplier->opening_balance = Money::amount($opening);
            $supplier->created_by = $user->id;
            $supplier->save();

            if (Money::cmp($opening, '0') !== 0) {
                $positive = Money::isPositive($opening);
                $this->ledger->post($supplier, 'opening', $supplier, now(config('app.display_timezone')), 'Opening balance',
                    $positive ? $opening : '0', $positive ? '0' : Money::neg($opening), $user);
            }

            return $supplier;
        });
    }

    /**
     * @param  array<int, array{material: Material, quantity: string, unit_cost: string, discount: ?string, tax: ?string}>  $lines
     */
    public function savePurchaseOrder(?PurchaseOrder $po, Supplier $supplier, Warehouse $warehouse, array $lines, array $data, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $supplier, $warehouse, $lines, $data, $user) {
            if ($po) {
                $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
                Approval::ensureStatus($po->status, 'draft', 'Only draft orders can be edited.');
                $po->lines()->delete();
            } else {
                $po = new PurchaseOrder(['po_no' => DocumentNumber::next('PO'), 'status' => 'draft', 'created_by' => $user->id]);
            }

            $po->fill([
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'project_id' => $warehouse->project_id,
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $po->save();

            $subtotal = $discount = $tax = '0';
            foreach ($lines as $line) {
                $gross = Money::mul($line['quantity'], $line['unit_cost']);
                $lineDiscount = Money::amount($line['discount'] ?? '0');
                $lineTax = Money::amount($line['tax'] ?? '0');
                if (Money::cmp($lineDiscount, $gross) > 0) {
                    throw ValidationException::withMessages(['lines' => 'A discount cannot exceed the line amount.']);
                }
                $po->lines()->create([
                    'material_id' => $line['material']->id,
                    'quantity' => Money::qty($line['quantity']),
                    'unit_price' => Money::rate($line['unit_cost']),
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => $lineTax,
                    'line_total' => Money::amount(Money::add(Money::sub($gross, $lineDiscount), $lineTax)),
                ]);
                [$subtotal, $discount, $tax] = [Money::add($subtotal, $gross), Money::add($discount, $lineDiscount), Money::add($tax, $lineTax)];
            }

            $po->forceFill([
                'subtotal' => Money::amount($subtotal), 'discount_amount' => Money::amount($discount), 'tax_amount' => Money::amount($tax),
                'total_amount' => Money::amount(Money::add(Money::sub($subtotal, $discount), $tax)), 'version' => $po->version + 1,
            ])->save();

            return $po;
        });
    }

    public function submit(PurchaseOrder $po, User $user): void
    {
        $this->transition($po, 'draft', 'submitted', fn ($p) => $p->submitted_at = now());
    }

    public function approve(PurchaseOrder $po, User $user): void
    {
        $this->transition($po, 'submitted', 'approved', function ($p) use ($user) {
            Approval::ensureNotSelf($user, $p->created_by, 'a purchase order');
            $p->approved_by = $user->id;
            $p->approved_at = now();
        });
    }

    public function reject(PurchaseOrder $po, string $reason, User $user): void
    {
        $this->transition($po, 'submitted', 'rejected', fn ($p) => [$p->rejection_reason = $reason, $p->approved_by = $user->id]);
    }

    public function cancel(PurchaseOrder $po, string $reason, User $user): void
    {
        $this->transition($po, ['draft', 'submitted', 'approved'], 'cancelled', function ($p) use ($reason) {
            if ($p->receipts()->exists()) {
                throw new ConflictHttpException('Goods were already received against this order.');
            }
            $p->rejection_reason = $reason;
        });
    }

    public function close(PurchaseOrder $po, User $user): void
    {
        $this->transition($po, ['partially_received', 'received'], 'closed', fn () => null);
    }

    /**
     * @param  array<int, array{received: string, accepted: string}>  $quantities  purchase order line id => quantities
     */
    public function receive(PurchaseOrder $po, array $quantities, array $data, User $user, string $key): GoodsReceipt
    {
        return DB::transaction(function () use ($po, $quantities, $data, $user, $key) {
            if ($existing = GoodsReceipt::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }

            $po = PurchaseOrder::query()->with(['lines.material', 'warehouse', 'project'])->lockForUpdate()->findOrFail($po->id);
            Approval::ensureStatus($po->status, PurchaseOrder::RECEIVABLE, 'Only approved orders can be received.');

            $chosen = [];
            foreach ($po->lines as $line) {
                $q = $quantities[$line->id] ?? null;
                if (! $q) {
                    continue;
                }
                $received = Money::qty($q['received'] ?? '0');
                $accepted = Money::qty($q['accepted'] ?? $received);
                if (Money::cmp($accepted, $received) > 0) {
                    throw ValidationException::withMessages(["quantities.{$line->id}.accepted" => 'Accepted cannot exceed received.']);
                }
                if (Money::cmp($accepted, $line->remaining()) > 0) {
                    throw new ConflictHttpException("Only {$line->remaining()} {$line->material->name} remains on this order.");
                }
                if (Money::isPositive($received)) {
                    $chosen[] = [$line, $received, $accepted];
                }
            }
            if (! $chosen) {
                throw ValidationException::withMessages(['quantities' => 'Enter a received quantity for at least one line.']);
            }

            $grn = GoodsReceipt::query()->create([
                'grn_no' => DocumentNumber::next('GRN'),
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'warehouse_id' => $po->warehouse_id,
                'received_at' => $data['received_at'],
                'supplier_challan_no' => $data['supplier_challan_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'posted',
                'received_by' => $user->id,
                'posted_at' => now(),
                'idempotency_key' => $key,
            ]);

            $specs = [];
            $total = '0';
            foreach ($chosen as [$line, $received, $accepted]) {
                $cost = $line->landedUnitCost();
                $grn->lines()->create([
                    'purchase_order_line_id' => $line->id, 'material_id' => $line->material_id, 'received_qty' => $received,
                    'accepted_qty' => $accepted, 'rejected_qty' => Money::qty(Money::sub($received, $accepted)), 'unit_cost' => $cost,
                ]);
                $line->forceFill(['received_qty' => Money::qty(Money::add($line->received_qty, $accepted))])->save();
                if (Money::isPositive($accepted)) {
                    $specs[] = ['type' => 'purchase_receipt', 'material' => $line->material, 'to' => $po->warehouse, 'qty' => $accepted, 'unit_cost' => $cost];
                    $total = Money::add($total, Money::mul($accepted, $cost));
                }
            }

            if ($specs) {
                $this->stock->post($grn, $specs, $user, 'grn|'.$key);
            }

            $po->status = $po->lines->every(fn (PurchaseOrderLine $l) => ! Money::isPositive($l->fresh()->remaining())) ? 'received' : 'partially_received';
            $po->version++;
            $po->save();

            $this->events->record('purchasing', $grn, 'purchase.received', Money::amount($total), Carbon::parse($data['received_at']),
                ['grn_no' => $grn->grn_no, 'po_no' => $po->po_no], project: $po->project, user: $user);

            return $grn;
        });
    }

    /**
     * @param  array<int, string>  $quantities  goods receipt line id => quantity
     */
    public function returnGoods(GoodsReceipt $grn, array $quantities, string $reason, User $user, string $key): PurchaseReturn
    {
        return DB::transaction(function () use ($grn, $quantities, $reason, $user, $key) {
            if ($existing = PurchaseReturn::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }

            $grn = GoodsReceipt::query()->with(['warehouse', 'supplier', 'purchaseOrder.project'])->lockForUpdate()->findOrFail($grn->id);
            $lines = GoodsReceiptLine::query()->where('goods_receipt_id', $grn->id)->with('material')->lockForUpdate()->get();

            $chosen = [];
            foreach ($lines as $line) {
                $qty = Money::qty($quantities[$line->id] ?? '0');
                if (Money::cmp($qty, $line->returnable()) > 0) {
                    throw new ConflictHttpException("Only {$line->returnable()} {$line->material->name} can be returned.");
                }
                if (Money::isPositive($qty)) {
                    $chosen[] = [$line, $qty];
                }
            }
            if (! $chosen) {
                throw ValidationException::withMessages(['quantities' => 'Enter a quantity for at least one line.']);
            }

            $return = PurchaseReturn::query()->create([
                'return_no' => DocumentNumber::next('PRN'),
                'supplier_id' => $grn->supplier_id,
                'goods_receipt_id' => $grn->id,
                'status' => 'posted',
                'reason' => $reason,
                'created_by' => $user->id,
                'posted_at' => now(),
                'idempotency_key' => $key,
            ]);

            $total = '0';
            $credit = '0';
            $specs = [];
            foreach ($chosen as [$line, $qty]) {
                $amount = Money::mul($qty, $line->unit_cost);
                $billed = $line->billed_bill_id !== null && SupplierBill::query()->whereKey($line->billed_bill_id)->whereIn('status', ['approved', 'paid'])->exists();
                $return->lines()->create([
                    'goods_receipt_line_id' => $line->id, 'material_id' => $line->material_id, 'quantity' => $qty,
                    'unit_cost' => $line->unit_cost, 'amount' => $amount, 'was_billed' => $billed,
                ]);
                $line->forceFill(['returned_qty' => Money::qty(Money::add($line->returned_qty, $qty))])->save();
                $specs[] = ['type' => 'purchase_return', 'material' => $line->material, 'from' => $grn->warehouse, 'qty' => $qty];
                $total = Money::add($total, $amount);
                // Only goods already billed need a credit; unbilled returns simply reduce what can be billed.
                if ($billed) {
                    $credit = Money::add($credit, $amount);
                }
            }

            $this->stock->post($return, $specs, $user, 'prn|'.$key);
            $return->forceFill(['total_amount' => Money::amount($total), 'credited' => Money::isPositive($credit)])->save();

            if (Money::isPositive($credit)) {
                $this->ledger->post($grn->supplier, 'return', $return, now(config('app.display_timezone')), 'Purchase return '.$return->return_no, '0', $credit, $user);
            }
            $this->events->record('purchasing', $return, 'purchase.returned', Money::amount($total), now(config('app.display_timezone')),
                ['return_no' => $return->return_no, 'grn_no' => $grn->grn_no, 'credited' => Money::amount($credit)], project: $grn->purchaseOrder->project, user: $user);

            return $return;
        });
    }

    /**
     * @param  array<int, int>  $receiptLineIds
     * @param  array<int, array{description: string, amount: string}>  $extras
     */
    public function createBill(Supplier $supplier, array $receiptLineIds, array $extras, string $tax, array $data, User $user): SupplierBill
    {
        return DB::transaction(function () use ($supplier, $receiptLineIds, $extras, $tax, $data, $user) {
            $lines = GoodsReceiptLine::query()->whereIn('id', $receiptLineIds)->with(['receipt', 'material'])->lockForUpdate()->get();

            if ($lines->count() !== count(array_unique($receiptLineIds))) {
                throw ValidationException::withMessages(['receipt_lines' => 'Some received lines could not be found.']);
            }
            foreach ($lines as $line) {
                if ($line->receipt->supplier_id !== $supplier->id) {
                    throw ValidationException::withMessages(['receipt_lines' => 'All received lines must belong to this supplier.']);
                }
                if ($line->billed_bill_id !== null) {
                    throw new ConflictHttpException("{$line->receipt->grn_no} {$line->material->name} is already billed.");
                }
                if (! Money::isPositive($line->returnable())) {
                    throw ValidationException::withMessages(['receipt_lines' => "{$line->material->name} on {$line->receipt->grn_no} has nothing left to bill."]);
                }
            }
            if ($lines->isEmpty() && ! $extras) {
                throw ValidationException::withMessages(['receipt_lines' => 'Select received goods or add a charge.']);
            }

            $bill = SupplierBill::query()->create([
                'bill_no' => DocumentNumber::next('SB'),
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $lines->pluck('receipt.purchase_order_id')->unique()->count() === 1 ? $lines->first()->receipt->purchase_order_id : null,
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => $user->id,
            ]);

            $subtotal = '0';
            foreach ($lines as $line) {
                $amount = $line->billableAmount();
                $bill->lines()->create([
                    'goods_receipt_line_id' => $line->id, 'description' => $line->receipt->grn_no.' · '.$line->material->name,
                    'quantity' => $line->returnable(), 'unit_price' => $line->unit_cost, 'amount' => $amount,
                ]);
                $line->forceFill(['billed_bill_id' => $bill->id])->save();
                $subtotal = Money::add($subtotal, $amount);
            }
            foreach ($extras as $extra) {
                $bill->lines()->create(['description' => $extra['description'], 'amount' => Money::amount($extra['amount'])]);
                $subtotal = Money::add($subtotal, $extra['amount']);
            }

            $bill->forceFill(['subtotal' => Money::amount($subtotal), 'tax_amount' => Money::amount($tax), 'total_amount' => Money::amount(Money::add($subtotal, $tax))])->save();

            return $bill;
        });
    }

    public function approveBill(SupplierBill $bill, User $user): void
    {
        DB::transaction(function () use ($bill, $user) {
            $bill = SupplierBill::query()->with('supplier')->lockForUpdate()->findOrFail($bill->id);
            Approval::ensureStatus($bill->status, 'draft', 'This bill is no longer a draft.');
            Approval::ensureNotSelf($user, $bill->created_by, 'a supplier bill');

            $bill->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
            $this->ledger->post($bill->supplier, 'bill', $bill, $bill->invoice_date, 'Bill '.$bill->bill_no.($bill->supplier_invoice_no ? ' / '.$bill->supplier_invoice_no : ''), $bill->total_amount, '0', $user);
            $this->events->record('purchasing', $bill, 'supplier.bill_approved', $bill->total_amount, $bill->invoice_date,
                ['bill_no' => $bill->bill_no, 'supplier' => $bill->supplier->supplier_code], project: $bill->purchaseOrder?->project, user: $user);
        });
    }

    public function cancelBill(SupplierBill $bill, User $user): void
    {
        DB::transaction(function () use ($bill) {
            $bill = SupplierBill::query()->lockForUpdate()->findOrFail($bill->id);
            Approval::ensureStatus($bill->status, 'draft', 'Only draft bills can be discarded.');
            GoodsReceiptLine::query()->where('billed_bill_id', $bill->id)->update(['billed_bill_id' => null]);
            $bill->forceFill(['status' => 'cancelled'])->save();
        });
    }

    public function reverseBill(SupplierBill $bill, string $reason, User $user): void
    {
        DB::transaction(function () use ($bill, $reason, $user) {
            $bill = SupplierBill::query()->lockForUpdate()->findOrFail($bill->id);
            Approval::ensureStatus($bill->status, 'approved', 'Only approved, unpaid bills can be reversed.');
            if (Money::isPositive($bill->paidAmount())) {
                throw new ConflictHttpException('Reverse the payments allocated to this bill first.');
            }

            GoodsReceiptLine::query()->where('billed_bill_id', $bill->id)->update(['billed_bill_id' => null]);
            $bill->forceFill(['status' => 'reversed', 'reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason])->save();
            $this->ledger->reverse($bill, 'bill', $reason, $user);
            $this->events->reverse('purchasing', $bill, now(config('app.display_timezone')), $user);
        });
    }

    /**
     * @param  array<int, string>  $allocations  bill id => amount
     */
    public function createPayment(Supplier $supplier, string $amount, array $allocations, array $data, User $user, string $key): SupplierPayment
    {
        return DB::transaction(function () use ($supplier, $amount, $allocations, $data, $user, $key) {
            if ($existing = SupplierPayment::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }
            $this->validateAllocations($supplier, $amount, $allocations, null);

            $payment = SupplierPayment::query()->create([
                'payment_no' => DocumentNumber::next('SP'),
                'supplier_id' => $supplier->id,
                'payment_date' => $data['payment_date'],
                'amount' => Money::amount($amount),
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'created_by' => $user->id,
                'idempotency_key' => $key,
            ]);
            foreach ($allocations as $billId => $value) {
                if (Money::isPositive($value)) {
                    $payment->allocations()->create(['supplier_bill_id' => $billId, 'amount' => Money::amount($value)]);
                }
            }

            return $payment;
        });
    }

    public function approvePayment(SupplierPayment $payment, User $user): void
    {
        DB::transaction(function () use ($payment, $user) {
            $payment = SupplierPayment::query()->with(['supplier', 'allocations'])->lockForUpdate()->findOrFail($payment->id);
            Approval::ensureStatus($payment->status, 'pending', 'This payment is no longer pending.');
            Approval::ensureNotSelf($user, $payment->created_by, 'a payment');

            $this->validateAllocations($payment->supplier, $payment->amount, $payment->allocations->pluck('amount', 'supplier_bill_id')->map(fn ($v) => (string) $v)->all(), $payment->id);

            $payment->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
            $this->ledger->post($payment->supplier, 'payment', $payment, $payment->payment_date, 'Payment '.$payment->payment_no.' ('.SupplierPayment::METHODS[$payment->payment_method].')', '0', $payment->amount, $user);

            foreach ($payment->allocations as $allocation) {
                $bill = $allocation->bill()->first();
                if (! Money::isPositive($bill->outstanding())) {
                    $bill->forceFill(['status' => 'paid'])->save();
                }
            }

            $this->events->record('purchasing', $payment, 'purchase.supplier_payment', $payment->amount, $payment->payment_date,
                ['payment_no' => $payment->payment_no, 'supplier' => $payment->supplier->supplier_code, 'method' => $payment->payment_method], user: $user);
        });
    }

    public function rejectPayment(SupplierPayment $payment, string $reason, User $user): void
    {
        DB::transaction(function () use ($payment, $reason, $user) {
            $payment = SupplierPayment::query()->lockForUpdate()->findOrFail($payment->id);
            Approval::ensureStatus($payment->status, 'pending', 'This payment is no longer pending.');
            $payment->forceFill(['status' => 'rejected', 'reversal_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public function reversePayment(SupplierPayment $payment, string $reason, User $user): void
    {
        DB::transaction(function () use ($payment, $reason, $user) {
            $payment = SupplierPayment::query()->with('allocations')->lockForUpdate()->findOrFail($payment->id);
            Approval::ensureStatus($payment->status, 'approved', 'Only approved payments can be reversed.');

            $payment->forceFill(['status' => 'reversed', 'reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason])->save();
            $this->ledger->reverse($payment, 'payment', $reason, $user);
            SupplierBill::query()->whereIn('id', $payment->allocations->pluck('supplier_bill_id'))->where('status', 'paid')->update(['status' => 'approved']);
            $this->events->reverse('purchasing', $payment, now(config('app.display_timezone')), $user);
        });
    }

    /**
     * @param  array<int, string>  $allocations  bill id => amount
     */
    private function validateAllocations(Supplier $supplier, string $amount, array $allocations, ?int $ignorePaymentId): void
    {
        if (! Money::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        $total = '0';
        $bills = SupplierBill::query()->whereIn('id', array_keys($allocations))->lockForUpdate()->get()->keyBy('id');
        foreach ($allocations as $billId => $value) {
            if (! Money::isPositive($value)) {
                continue;
            }
            $bill = $bills[$billId] ?? null;
            if (! $bill || $bill->supplier_id !== $supplier->id || ! in_array($bill->status, ['approved', 'paid'], true)) {
                throw ValidationException::withMessages(["allocations.{$billId}" => 'Pay only approved bills of this supplier.']);
            }
            if (Money::cmp($value, $bill->outstanding()) > 0) {
                throw ValidationException::withMessages(["allocations.{$billId}" => 'Cannot pay more than the outstanding '.Money::amount($bill->outstanding()).'.']);
            }
            $total = Money::add($total, $value);
        }

        if (Money::cmp($total, $amount) > 0) {
            throw ValidationException::withMessages(['amount' => 'Allocations exceed the payment amount.']);
        }
    }

    private function transition(PurchaseOrder $po, array|string $from, string $to, callable $mutate): void
    {
        DB::transaction(function () use ($po, $from, $to, $mutate) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
            Approval::ensureStatus($po->status, $from, 'This order cannot be '.str_replace('_', ' ', $to).' now.');
            $mutate($po);
            $po->status = $to;
            $po->version++;
            $po->save();
        });
    }
}
