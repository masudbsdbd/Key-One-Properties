<?php

namespace Tests\Feature\Phase2;

use App\Models\GoodsReceipt;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Purchasing\Services\PurchasingService;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchasingHttpTest extends TestCase
{
    private const BUYER = ['projects.view', 'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.ledger.view', 'suppliers.ledger.export',
        'purchases.view', 'purchases.create', 'purchases.receive', 'purchases.return', 'purchases.bill.manage', 'purchases.payment.create'];

    private const APPROVER = ['projects.view', 'suppliers.view', 'purchases.view', 'purchases.approve', 'purchases.bill.approve', 'purchases.payment.approve'];

    private Project $project;

    private Warehouse $warehouse;

    private Material $cement;

    private Material $rod;

    private User $buyer;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create();
        $this->buyer = $this->userWith(self::BUYER);
        $this->approver = $this->userWith(self::APPROVER);
        $this->project->members()->attach([$this->buyer->id, $this->approver->id]);

        $this->warehouse = new Warehouse(['code' => 'WH-S', 'name' => 'Site Store']);
        $this->warehouse->project_id = $this->project->id;
        $this->warehouse->save();

        $cat = MaterialCategory::query()->create(['code' => 'GEN', 'name' => 'General']);
        $unit = MaterialUnit::query()->create(['code' => 'BAG', 'name' => 'Bag']);
        foreach (['cement' => ['CEM', 'Cement'], 'rod' => ['ROD', 'MS Rod']] as $prop => [$sku, $name]) {
            $m = new Material(['sku' => $sku, 'name' => $name]);
            $m->category_id = $cat->id;
            $m->unit_id = $unit->id;
            $m->save();
            $this->{$prop} = $m;
        }
    }

    private function supplier(): Supplier
    {
        $this->actingAs($this->buyer)->ajax()->post(route('suppliers.store'), [
            'name' => 'Shah Traders', 'mobile' => '01711000000', 'status' => 'active', 'opening_balance' => '1,000',
        ])->assertOk();

        return Supplier::query()->where('name', 'Shah Traders')->firstOrFail();
    }

    private function approvedOrder(Supplier $supplier): PurchaseOrder
    {
        $this->actingAs($this->buyer)->ajax()->post(route('purchase-orders.store'), [
            'supplier' => $supplier->public_id, 'warehouse' => $this->warehouse->public_id, 'order_date' => now()->toDateString(),
            'lines' => [
                ['material' => $this->cement->public_id, 'quantity' => '100', 'unit_cost' => '500', 'discount' => '1000', 'tax' => ''],
                ['material' => $this->rod->public_id, 'quantity' => '50', 'unit_cost' => '90', 'discount' => '', 'tax' => '0'],
            ],
        ])->assertOk();
        $po = PurchaseOrder::query()->latest('id')->firstOrFail();
        $this->assertSame('53500.00', $po->total_amount);

        $this->actingAs($this->buyer)->ajax()->post(route('purchase-orders.submit', $po))->assertOk();
        $this->actingAs($this->buyer)->ajax()->post(route('purchase-orders.approve', $po))->assertForbidden();
        $this->actingAs($this->approver)->ajax()->post(route('purchase-orders.approve', $po))->assertOk();

        return $po->fresh();
    }

    public function test_full_purchase_cycle_over_http(): void
    {
        $supplier = $this->supplier();
        $this->assertSame('1000.00', $supplier->balance());
        $po = $this->approvedOrder($supplier);

        $this->actingAs($this->buyer)->get(route('purchase-orders.show', $po))->assertOk()->assertSee($po->po_no);
        $form = $this->actingAs($this->buyer)->ajax()->get(route('purchase-orders.receive.form', $po))->assertOk()->json('data.html');
        $this->assertStringContainsString('quantities[0][received]', $form);
        $this->assertStringContainsString('quantities[1][received]', $form);

        // Partial receipt: positions map to order lines; accepted above received is rejected on the right field.
        $this->actingAs($this->buyer)->ajax()->post(route('purchase-orders.receive', $po), [
            'received_at' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid(),
            'quantities' => [0 => ['received' => '40', 'accepted' => '45']],
        ])->assertStatus(422)->assertJsonValidationErrors('quantities.0.accepted');
        $this->actingAs($this->buyer)->ajax()->post(route('purchase-orders.receive', $po), [
            'received_at' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid(),
            'quantities' => [0 => ['received' => '200']],
        ])->assertStatus(409);
        $key = (string) Str::uuid();
        $payload = ['received_at' => now()->toDateString(), 'idempotency_key' => $key, 'quantities' => [0 => ['received' => '40', 'accepted' => '38'], 1 => ['received' => '50']]];
        $this->actingAs($this->buyer)->ajax()->post(route('purchase-orders.receive', $po), $payload)->assertOk();
        $this->actingAs($this->buyer)->ajax()->post(route('purchase-orders.receive', $po), $payload)->assertOk();
        $this->assertSame(1, GoodsReceipt::query()->count());
        $this->assertSame('partially_received', $po->fresh()->status);
        $grn = GoodsReceipt::query()->firstOrFail();

        // Return before billing: no supplier credit.
        $this->actingAs($this->buyer)->ajax()->post(route('purchase-orders.return', [$po, $grn]), [
            'quantities' => [1 => '5'], 'reason' => 'Bent rods', 'idempotency_key' => (string) Str::uuid(),
        ])->assertOk();
        $this->assertSame('1000.00', $supplier->balance());

        // Bill the rest of the receipt plus transport.
        $billable = $this->actingAs($this->buyer)->ajax()->get(route('supplier-bills.billable', ['supplier' => $supplier->public_id]))->assertOk()->json('data.html');
        $this->assertStringContainsString($grn->public_id.':0', $billable);
        $this->actingAs($this->buyer)->ajax()->post(route('supplier-bills.store'), [
            'supplier' => $supplier->public_id, 'invoice_date' => now()->toDateString(), 'tax_amount' => '0',
            'receipt_lines' => [$grn->public_id.':0', $grn->public_id.':1'],
            'charges' => [['description' => 'Transport', 'amount' => '500']],
        ])->assertOk();
        $bill = SupplierBill::query()->firstOrFail();
        // 38 x 490 (discount spread) + 45 x 90 + 500
        $this->assertSame('23170.00', $bill->total_amount);
        $this->actingAs($this->buyer)->ajax()->post(route('supplier-bills.store'), [
            'supplier' => $supplier->public_id, 'invoice_date' => now()->toDateString(), 'tax_amount' => '0', 'receipt_lines' => [$grn->public_id.':0'],
        ])->assertStatus(409);

        $this->actingAs($this->buyer)->ajax()->post(route('supplier-bills.approve', $bill))->assertForbidden();
        $this->actingAs($this->approver)->ajax()->post(route('supplier-bills.approve', $bill))->assertOk();
        $this->assertSame('24170.00', $supplier->balance());

        // Over-allocation is rejected on the bill field; duplicate submit creates one payment.
        $this->actingAs($this->buyer)->ajax()->post(route('supplier-payments.store'), [
            'supplier' => $supplier->public_id, 'payment_date' => now()->toDateString(), 'amount' => '30000', 'payment_method' => 'cash',
            'allocations' => [$bill->public_id => '23170.01'], 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(422)->assertJsonValidationErrors('allocations.'.$bill->public_id);
        $payment = ['supplier' => $supplier->public_id, 'payment_date' => now()->toDateString(), 'amount' => '24,170', 'payment_method' => 'bank',
            'payment_reference' => 'TRX-1', 'allocations' => [$bill->public_id => '23170'], 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($this->buyer)->ajax()->post(route('supplier-payments.store'), $payment)->assertOk();
        $this->actingAs($this->buyer)->ajax()->post(route('supplier-payments.store'), $payment)->assertOk();
        $this->assertSame(1, SupplierPayment::query()->count());

        $sp = SupplierPayment::query()->firstOrFail();
        $this->actingAs($this->buyer)->ajax()->post(route('supplier-payments.approve', $sp))->assertForbidden();
        $this->actingAs($this->approver)->ajax()->post(route('supplier-payments.approve', $sp))->assertOk();
        $this->assertSame('0.00', $supplier->balance());
        $this->assertSame('paid', $bill->fresh()->status);

        // Statement: running balance and CSV.
        $ledger = $this->actingAs($this->buyer)->ajax()->get(route('suppliers.ledger', $supplier))->assertOk()->json('data.html');
        $this->assertStringContainsString('24,170', $ledger);
        $csv = $this->actingAs($this->buyer)->get(route('suppliers.ledger.export', $supplier))->assertOk()->streamedContent();
        $this->assertStringContainsString('Opening Balance', $csv);
        $this->assertStringEndsWith(",0.00\n", $csv);

        foreach ([route('suppliers.show', $supplier), route('supplier-bills.show', $bill), route('supplier-payments.show', $sp),
            route('suppliers.index'), route('purchase-orders.index'), route('supplier-bills.index'), route('supplier-payments.index')] as $url) {
            $this->actingAs($this->buyer)->get($url)->assertOk();
        }
        foreach (['suppliers.table', 'purchase-orders.table', 'supplier-bills.table', 'supplier-payments.table'] as $name) {
            $this->actingAs($this->buyer)->ajax()->get(route($name))->assertOk();
        }
    }

    public function test_project_orders_are_hidden_from_users_outside_the_project(): void
    {
        $po = $this->approvedOrder($this->supplier());
        $outsider = $this->userWith(self::BUYER);

        $this->actingAs($outsider)->get(route('purchase-orders.show', $po))->assertForbidden();
        $this->actingAs($outsider)->ajax()->get(route('purchase-orders.receive.form', $po))->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('purchase-orders.receive', $po), [
            'received_at' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid(), 'quantities' => [0 => ['received' => '1']],
        ])->assertForbidden();
        $html = $this->actingAs($outsider)->ajax()->get(route('purchase-orders.table'))->assertOk()->json('data.html');
        $this->assertStringNotContainsString($po->po_no, $html);
    }

    public function test_viewers_cannot_change_purchasing_data(): void
    {
        $supplier = $this->supplier();
        $viewer = $this->userWith(['projects.view', 'suppliers.view', 'purchases.view']);

        $this->actingAs($viewer)->ajax()->post(route('suppliers.store'), ['name' => 'X', 'status' => 'active', 'opening_balance' => '0'])->assertForbidden();
        $this->actingAs($viewer)->ajax()->put(route('suppliers.update', $supplier), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->actingAs($viewer)->ajax()->get(route('suppliers.ledger', $supplier))->assertForbidden();
        $this->actingAs($viewer)->get(route('suppliers.ledger.export', $supplier))->assertForbidden();
        $this->actingAs($viewer)->ajax()->get(route('supplier-bills.create'))->assertForbidden();
        $this->actingAs($viewer)->ajax()->get(route('supplier-payments.create'))->assertForbidden();
    }

    public function test_payments_follow_the_projects_of_the_bills_they_settle(): void
    {
        $supplier = $this->supplier();
        $po = $this->approvedOrder($supplier);
        $line = $po->lines()->orderBy('id')->first();
        $service = app(PurchasingService::class);
        $grn = $service->receive($po, [$line->id => ['received' => '10', 'accepted' => '10']], ['received_at' => now()->toDateString()], $this->buyer, (string) Str::uuid());
        $bill = $service->createBill($supplier, [$grn->lines()->first()->id], [], '0', ['invoice_date' => now()->toDateString()], $this->buyer);
        $service->approveBill($bill, $this->approver);
        $paid = $service->createPayment($supplier, '100', [$bill->id => '100'], ['payment_date' => now()->toDateString(), 'payment_method' => 'cash'], $this->buyer, (string) Str::uuid());
        $advance = $service->createPayment($supplier, '50', [], ['payment_date' => now()->toDateString(), 'payment_method' => 'cash'], $this->buyer, (string) Str::uuid());

        $outsider = $this->userWith(array_merge(self::BUYER, ['purchases.payment.approve']));
        $this->actingAs($outsider)->get(route('supplier-payments.show', $paid))->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('supplier-payments.approve', $paid))->assertForbidden();
        $this->actingAs($outsider)->get(route('supplier-payments.document', $paid))->assertForbidden();
        $this->actingAs($outsider)->get(route('supplier-payments.show', $advance))->assertOk();
        $html = $this->actingAs($outsider)->ajax()->get(route('supplier-payments.table'))->json('data.html');
        $this->assertStringNotContainsString($paid->payment_no, $html);
        $this->assertStringContainsString($advance->payment_no, $html);
        $this->actingAs($this->buyer)->get(route('supplier-payments.show', $paid))->assertOk();
    }
}
