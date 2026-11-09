<?php

namespace Tests\Feature\Phase2;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Support\Format;
use Tests\TestCase;

class ExportTest extends TestCase
{
    public function test_reports_render_as_csv_pdf_and_print(): void
    {
        $user = $this->userWith(['suppliers.view', 'suppliers.ledger.view', 'suppliers.ledger.export', 'expenses.view.general', 'expenses.export']);
        $supplier = app(PurchasingService::class)->createSupplier(['name' => 'Acme Cement', 'status' => 'active'], '12500', $user);
        $category = ExpenseCategory::query()->create(['code' => 'X', 'name' => 'Misc']);
        Expense::query()->create(['expense_no' => 'EXP-T-1', 'scope' => 'general', 'category_id' => $category->id, 'expense_date' => now(),
            'description' => 'Office tea', 'amount' => '1500', 'status' => 'approved', 'created_by' => $user->id]);

        foreach ([route('suppliers.ledger.export', $supplier), route('expenses.export')] as $url) {
            $csv = $this->actingAs($user)->get($url)->assertOk()->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

            $pdf = $this->actingAs($user)->get($url.'?format=pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $pdf->getContent());

            $this->actingAs($user)->get($url.'?format=print')->assertOk()->assertSee('data-print', false);
        }

        $this->actingAs($user)->get(route('suppliers.ledger.export', $supplier).'?format=print')->assertSee('Acme Cement')->assertSee('৳ 12,500');
        $this->actingAs($user)->get(route('expenses.export').'?format=print')->assertSee('Office tea')->assertSee('EXP-T-1');
        $this->assertFalse(Format::$plainSymbol);
    }

    public function test_every_list_export_renders_in_all_formats_for_a_super_admin(): void
    {
        $admin = $this->superAdmin();
        $routes = ['suppliers.export', 'purchase-orders.export', 'supplier-bills.export', 'supplier-payments.export', 'contractors.export', 'work-orders.export',
            'contractor-bills.export', 'contractor-payments.export', 'stock.export', 'stock.movements.export', 'labour.export', 'flats.export', 'customers.export',
            'projects.export', 'expenses.export'];

        foreach ($routes as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
            $this->assertStringStartsWith('%PDF', $this->actingAs($admin)->get(route($name, ['format' => 'pdf']))->assertOk()->getContent(), $name);
            $this->actingAs($admin)->get(route($name, ['format' => 'print']))->assertOk();
        }
    }

    public function test_list_exports_need_their_export_permission(): void
    {
        $viewer = $this->userWith(['purchases.view', 'suppliers.view', 'contractors.view', 'customers.view', 'flats.view', 'projects.view', 'materials.view']);
        foreach (['suppliers.export', 'purchase-orders.export', 'contractors.export', 'work-orders.export', 'customers.export', 'flats.export', 'projects.export', 'stock.export'] as $name) {
            $this->actingAs($viewer)->get(route($name))->assertForbidden();
        }
    }
}
