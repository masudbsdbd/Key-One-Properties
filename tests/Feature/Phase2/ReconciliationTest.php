<?php

namespace Tests\Feature\Phase2;

use App\Models\ContractorWorkOrder;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\User;
use App\Modules\Contractors\Services\ContractorService;
use App\Modules\Expenses\Services\ExpenseService;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Support\Reconciliation\SubledgerReconciler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    public function test_posted_documents_reconcile_and_tampering_is_detected(): void
    {
        [$clerk, $boss] = [User::factory()->create(), User::factory()->create()];
        $project = Project::factory()->create();

        $purchasing = app(PurchasingService::class);
        $supplier = $purchasing->createSupplier(['name' => 'Acme', 'status' => 'active'], '500', $clerk);
        $payment = $purchasing->createPayment($supplier, '200', [], ['payment_date' => now()->toDateString(), 'payment_method' => 'cash'], $clerk, (string) Str::uuid());
        $purchasing->approvePayment($payment, $boss);
        $purchasing->reversePayment($payment->fresh(), 'Wrong supplier', $boss);

        $contractors = app(ContractorService::class);
        $contractor = $contractors->createContractor(['name' => 'Builder', 'status' => 'active'], $clerk);
        $wo = $contractors->saveWorkOrder(null, $contractor, $project, null, ['title' => 'Walls', 'contract_amount' => '1000', 'retention_percent' => '10'], $clerk);
        $contractors->approveWorkOrder($wo, $boss);
        $m = $contractors->recordMeasurement($wo, ['measurement_date' => now()->toDateString(), 'description' => 'L1', 'quantity' => '10', 'unit' => 'sft', 'rate' => '50'], $clerk);
        $contractors->approveMeasurement($m, $boss);
        $bill = $contractors->createBill($wo, [$m->id], ['bill_date' => now()->toDateString()], $clerk);
        $contractors->approveBill($bill, $boss);

        $expenses = app(ExpenseService::class);
        $category = ExpenseCategory::query()->create(['code' => 'X', 'name' => 'Misc']);
        $expense = $expenses->save(null, $category, $project, null, ['scope' => 'project', 'expense_date' => now()->toDateString(), 'description' => 'Fuel', 'amount' => '75'], $clerk);
        $expenses->submit($expense, $clerk);
        $expenses->approve($expense, $boss);

        $reconciler = app(SubledgerReconciler::class);
        $this->assertSame([], $reconciler->differences());
        $this->artisan('keyone:reconcile')->assertSuccessful();

        DB::table('contractor_ledger_entries')->insert([
            'contractor_id' => $contractor->id, 'entry_type' => 'payment', 'source_type' => (new ContractorWorkOrder)->getMorphClass(), 'source_id' => $wo->id,
            'entry_date' => now()->toDateString(), 'description' => 'Manual', 'charge_amount' => 0, 'payment_amount' => 10, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('project_cost_allocations')->where('source_id', $expense->id)->update(['amount' => 70]);

        $diffs = collect($reconciler->differences());
        $this->assertSame(['Contractor ledger', 'Project cost'], $diffs->pluck('check')->unique()->values()->all());
        $this->artisan('keyone:reconcile')->assertFailed();
    }
}
