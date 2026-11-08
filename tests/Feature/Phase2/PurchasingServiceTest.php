<?php

namespace Tests\Feature\Phase2;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Inventory\Services\StockReconciler;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Support\Costing\CostAttribution;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class PurchasingServiceTest extends TestCase
{
    private PurchasingService $service;

    private Supplier $supplier;

    private Warehouse $warehouse;

    private Material $cement;

    private User $buyer;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PurchasingService::class);
        $this->buyer = User::factory()->create();
        $this->approver = User::factory()->create();
        $this->supplier = $this->service->createSupplier(['name' => 'Shah Cement', 'status' => 'active'], '0', $this->buyer);

        $project = Project::factory()->create();
        $this->warehouse = new Warehouse(['code' => 'WH-1', 'name' => 'Site']);
        $this->warehouse->project_id = $project->id;
        $this->warehouse->save();

        $cat = MaterialCategory::query()->create(['code' => 'C', 'name' => 'Cement']);
        $unit = MaterialUnit::query()->create(['code' => 'BAG', 'name' => 'Bag']);
        $this->cement = new Material(['sku' => 'CEM', 'name' => 'Cement']);
        $this->cement->category_id = $cat->id;
        $this->cement->unit_id = $unit->id;
        $this->cement->save();
    }

    private function approvedPo(string $qty = '100', string $price = '500'): PurchaseOrder
    {
        $po = $this->service->savePurchaseOrder(null, $this->supplier, $this->warehouse,
            [['material' => $this->cement, 'quantity' => $qty, 'unit_cost' => $price, 'discount' => '0', 'tax' => '0']],
            ['order_date' => now()->toDateString()], $this->buyer);
        $this->service->submit($po, $this->buyer);
        $this->service->approve($po, $this->approver);

        return $po->fresh('lines');
    }

    private function owned(): string
    {
        return StockBalance::query()->where('warehouse_id', $this->warehouse->id)->value('owned_qty') ?? '0';
    }

    public function test_po_totals_are_computed_on_the_server_and_self_approval_is_blocked(): void
    {
        $po = $this->service->savePurchaseOrder(null, $this->supplier, $this->warehouse,
            [['material' => $this->cement, 'quantity' => '100', 'unit_cost' => '500', 'discount' => '1000', 'tax' => '2500']],
            ['order_date' => now()->toDateString()], $this->buyer);

        $this->assertSame('50000.00', $po->subtotal);
        $this->assertSame('51500.00', $po->total_amount);
        $this->assertSame('515.0000', $po->lines->first()->landedUnitCost());
        $this->assertSame($this->warehouse->project_id, $po->project_id);

        $this->service->submit($po, $this->buyer);
        $this->expectException(ValidationException::class);
        $this->service->approve($po, $this->buyer);
    }

    public function test_partial_receipt_return_and_retry(): void
    {
        $po = $this->approvedPo();
        $line = $po->lines->first();
        $key = (string) Str::uuid();

        $grn = $this->service->receive($po, [$line->id => ['received' => '60', 'accepted' => '55']], ['received_at' => now()->toDateString()], $this->buyer, $key);
        $this->service->receive($po, [$line->id => ['received' => '60', 'accepted' => '55']], ['received_at' => now()->toDateString()], $this->buyer, $key);

        $this->assertSame('55.000', $this->owned());
        $this->assertSame('partially_received', $po->fresh()->status);
        $this->assertSame('5.000', $grn->lines->first()->rejected_qty);

        try {
            $this->service->receive($po, [$line->id => ['received' => '50', 'accepted' => '50']], ['received_at' => now()->toDateString()], $this->buyer, (string) Str::uuid());
            $this->fail('Over-receipt must fail.');
        } catch (ConflictHttpException) {
        }

        $this->service->returnGoods($grn, [$grn->lines->first()->id => '5'], 'Damaged', $this->buyer, (string) Str::uuid());
        $this->assertSame('50.000', $this->owned());
        $this->assertSame('0.00', $this->supplier->balance(), 'An unbilled return creates no credit.');

        $this->service->receive($po, [$line->id => ['received' => '45', 'accepted' => '45']], ['received_at' => now()->toDateString()], $this->buyer, (string) Str::uuid());
        $this->assertSame('received', $po->fresh()->status);
        $this->assertSame([], app(StockReconciler::class)->differences());
        $this->assertSame('0.00', CostAttribution::totals($this->warehouse->project)['material'], 'Receiving is inventory, not project cost.');
    }

    public function test_bill_payment_outstanding_and_allocation_limits(): void
    {
        $po = $this->approvedPo('10', '10');
        $grn = $this->service->receive($po, [$po->lines->first()->id => ['received' => '10', 'accepted' => '10']], ['received_at' => now()->toDateString()], $this->buyer, (string) Str::uuid());

        $bill = $this->service->createBill($this->supplier, $grn->lines->pluck('id')->all(), [], '0', ['invoice_date' => now()->toDateString()], $this->buyer);
        $this->assertSame('100.00', $bill->total_amount);

        try {
            $this->service->createBill($this->supplier, $grn->lines->pluck('id')->all(), [], '0', ['invoice_date' => now()->toDateString()], $this->buyer);
            $this->fail('A received line cannot be billed twice.');
        } catch (ConflictHttpException) {
        }

        $this->service->approveBill($bill, $this->approver);
        $this->assertSame('100.00', $this->supplier->balance());

        $payment = $this->service->createPayment($this->supplier, '40', [$bill->id => '40'], ['payment_date' => now()->toDateString(), 'payment_method' => 'bank'], $this->buyer, (string) Str::uuid());
        $this->service->approvePayment($payment, $this->approver);
        $this->assertSame('60.00', $bill->fresh()->outstanding());
        $this->assertSame('60.00', $this->supplier->balance());

        try {
            $this->service->createPayment($this->supplier, '70', [$bill->id => '70'], ['payment_date' => now()->toDateString(), 'payment_method' => 'cash'], $this->buyer, (string) Str::uuid());
            $this->fail('Allocation above outstanding must fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey("allocations.{$bill->id}", $e->errors());
        }

        try {
            $this->service->createPayment($this->supplier, '10', [$bill->id => '20'], ['payment_date' => now()->toDateString(), 'payment_method' => 'cash'], $this->buyer, (string) Str::uuid());
            $this->fail('Allocations above the payment must fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $final = $this->service->createPayment($this->supplier, '60', [$bill->id => '60'], ['payment_date' => now()->toDateString(), 'payment_method' => 'cash'], $this->buyer, (string) Str::uuid());
        $this->service->approvePayment($final, $this->approver);
        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertSame('0.00', $this->supplier->balance());

        $this->service->reversePayment($final, 'Bounced cheque', $this->approver);
        $this->assertSame('approved', $bill->fresh()->status);
        $this->assertSame('60.00', $this->supplier->balance());
    }

    public function test_return_after_billing_credits_the_supplier(): void
    {
        $po = $this->approvedPo('10', '10');
        $grn = $this->service->receive($po, [$po->lines->first()->id => ['received' => '10', 'accepted' => '10']], ['received_at' => now()->toDateString()], $this->buyer, (string) Str::uuid());
        $bill = $this->service->createBill($this->supplier, $grn->lines->pluck('id')->all(), [['description' => 'Transport', 'amount' => '15']], '5', ['invoice_date' => now()->toDateString()], $this->buyer);
        $this->service->approveBill($bill, $this->approver);
        $this->assertSame('120.00', $this->supplier->balance());

        $this->service->returnGoods($grn, [$grn->lines->first()->id => '3'], 'Wet bags', $this->buyer, (string) Str::uuid());
        $this->assertSame('90.00', $this->supplier->balance());
    }

    public function test_bill_reversal_frees_lines_and_restores_balance(): void
    {
        $po = $this->approvedPo('10', '10');
        $grn = $this->service->receive($po, [$po->lines->first()->id => ['received' => '10', 'accepted' => '10']], ['received_at' => now()->toDateString()], $this->buyer, (string) Str::uuid());
        $bill = $this->service->createBill($this->supplier, $grn->lines->pluck('id')->all(), [], '0', ['invoice_date' => now()->toDateString()], $this->buyer);
        $this->service->approveBill($bill, $this->approver);
        $this->service->reverseBill($bill, 'Wrong supplier invoice', $this->approver);

        $this->assertSame('0.00', $this->supplier->balance());
        $this->assertNull($grn->lines->first()->fresh()->billed_bill_id);
        $this->assertDatabaseCount('supplier_ledger_entries', 2);
    }
}
