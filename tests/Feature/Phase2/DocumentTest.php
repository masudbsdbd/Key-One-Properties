<?php

namespace Tests\Feature\Phase2;

use App\Models\ExpenseCategory;
use App\Models\GoodsReceipt;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Project;
use App\Models\PurchaseReturn;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Contractors\Services\ContractorService;
use App\Modules\Expenses\Services\ExpenseService;
use App\Modules\Purchasing\Services\PurchasingService;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    public function test_documents_print_and_download_and_respect_project_access(): void
    {
        [$clerk, $boss] = [User::factory()->create(), User::factory()->create()];
        $project = Project::factory()->create();
        $warehouse = new Warehouse(['code' => 'WH-D', 'name' => 'Site']);
        $warehouse->project_id = $project->id;
        $warehouse->save();
        $material = new Material(['sku' => 'CEM', 'name' => 'Cement']);
        $material->category_id = MaterialCategory::query()->create(['code' => 'C', 'name' => 'Cement'])->id;
        $material->unit_id = MaterialUnit::query()->create(['code' => 'BAG', 'name' => 'Bag'])->id;
        $material->save();

        $p = app(PurchasingService::class);
        $supplier = $p->createSupplier(['name' => 'Acme', 'status' => 'active'], '0', $clerk);
        $po = $p->savePurchaseOrder(null, $supplier, $warehouse, [['material' => $material, 'quantity' => '10', 'unit_cost' => '500', 'discount' => '0', 'tax' => '0']], ['order_date' => now()->toDateString()], $clerk);
        $p->submit($po, $clerk);
        $p->approve($po, $boss);
        $line = $po->lines()->first();
        $grn = $p->receive($po->fresh(), [$line->id => ['received' => '10', 'accepted' => '10']], ['received_at' => now()->toDateString()], $clerk, (string) Str::uuid());
        $p->returnGoods($grn, [$grn->lines()->first()->id => '2'], 'Damaged', $clerk, (string) Str::uuid());
        $bill = $p->createBill($supplier, [$grn->lines()->first()->id], [], '0', ['invoice_date' => now()->toDateString()], $clerk);
        $p->approveBill($bill, $boss);
        $payment = $p->createPayment($supplier, '1000', [$bill->id => '1000'], ['payment_date' => now()->toDateString(), 'payment_method' => 'cash'], $clerk, (string) Str::uuid());

        $c = app(ContractorService::class);
        $wo = $c->saveWorkOrder(null, $c->createContractor(['name' => 'Builder', 'status' => 'active'], $clerk), $project, null, ['title' => 'Walls', 'contract_amount' => '1000'], $clerk);
        $c->approveWorkOrder($wo, $boss);
        $m = $c->recordMeasurement($wo, ['measurement_date' => now()->toDateString(), 'description' => 'L1', 'quantity' => '10', 'unit' => 'sft', 'rate' => '50'], $clerk);
        $c->approveMeasurement($m, $boss);
        $cbill = $c->createBill($wo, [$m->id], ['bill_date' => now()->toDateString()], $clerk);
        $c->approveBill($cbill, $boss);
        $cpay = $c->createPayment($wo, $cbill->fresh(), '200', ['payment_date' => now()->toDateString(), 'payment_method' => 'cash'], $clerk, (string) Str::uuid());

        $expense = app(ExpenseService::class)->save(null, ExpenseCategory::query()->create(['code' => 'X', 'name' => 'Misc']), $project, null,
            ['scope' => 'project', 'expense_date' => now()->toDateString(), 'description' => 'Fuel', 'payee_name' => 'Padma Oil', 'amount' => '75'], $clerk);

        $urls = [
            route('purchase-orders.document', $po) => $po->po_no,
            route('purchase-orders.receipt.document', [$po, $grn]) => $grn->grn_no,
            route('purchase-orders.return.document', [$po, PurchaseReturn::query()->first()]) => 'Damaged',
            route('supplier-bills.document', $bill) => $bill->bill_no,
            route('supplier-payments.document', $payment) => $payment->payment_no,
            route('work-orders.document', $wo) => $wo->wo_no,
            route('contractor-bills.document', $cbill) => $cbill->bill_no,
            route('contractor-payments.document', $cpay) => $cpay->payment_no,
            route('expenses.document', $expense) => 'Padma Oil',
        ];
        $admin = $this->superAdmin();
        foreach ($urls as $url => $text) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee($text);
            $this->assertStringStartsWith('%PDF', $this->actingAs($admin)->get($url.'?format=pdf')->assertOk()->getContent());
        }

        $outsider = $this->userWith(['projects.view', 'purchases.view', 'contractors.view', 'expenses.view.project']);
        foreach ([route('purchase-orders.document', $po), route('work-orders.document', $wo), route('contractor-bills.document', $cbill), route('expenses.document', $expense)] as $url) {
            $this->actingAs($outsider)->get($url)->assertForbidden();
        }
        $otherPo = $p->savePurchaseOrder(null, $supplier, $warehouse, [['material' => $material, 'quantity' => '1', 'unit_cost' => '1', 'discount' => '0', 'tax' => '0']], ['order_date' => now()->toDateString()], $clerk);
        $this->actingAs($admin)->get(route('purchase-orders.receipt.document', [$otherPo, GoodsReceipt::query()->first()]))->assertNotFound();
    }
}
