<?php

namespace Tests\Feature\Phase3;

use App\Models\CustomerPayment;
use App\Models\FlatSale;
use App\Models\User;
use App\Support\Format;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SampleDataSeeder;
use Database\Seeders\SuperAdminSeeder;
use Tests\TestCase;

class RecordPagesSalesTest extends TestCase
{
    private FlatSale $sale;

    protected function setUp(): void
    {
        parent::setUp();
        config(['keyone.super_admin.password' => 'Admin@12345']);
        $this->seed([SuperAdminSeeder::class, ExpenseCategorySeeder::class, SampleDataSeeder::class, SampleDataSeeder::class]);
        $this->sale = FlatSale::query()->with(['flat', 'customer', 'project'])->firstOrFail();
    }

    public function test_unit_page_shows_the_current_sale_with_its_collection_position(): void
    {
        $admin = User::query()->where('email', 'admin@gmail.com')->firstOrFail();
        $paid = (float) CustomerPayment::query()->where('customer_id', $this->sale->customer_id)->where('status', 'posted')->sum('amount');

        $this->actingAs($admin)->get(route('flats.show', $this->sale->flat))->assertOk()
            ->assertSee($this->sale->sale_no)->assertSee($this->sale->customer->full_name)
            ->assertSee(Format::money($paid))->assertDontSee('Coming in Phase')
            ->assertSee('Sold To')->assertSee('Agreement Signed')->assertSee('Down Payment')
            ->assertSee(route('customers.show', $this->sale->customer), false);
        CustomerPayment::query()->where('sale_id', $this->sale->id)->where('status', 'posted')->pluck('payment_no')
            ->each(fn ($no) => $this->actingAs($admin)->get(route('flats.show', $this->sale->flat))->assertSee($no));

        $salesOnly = $this->userWith(['flats.view', 'projects.view_all', 'sales.sale.view']);
        $this->actingAs($salesOnly)->get(route('flats.show', $this->sale->flat))->assertOk()
            ->assertSee('Sold To')->assertDontSee('id="deal-tab-payments"', false)->assertDontSee('id="deal-tab-ledger"', false);

        $viewer = $this->userWith(['flats.view', 'projects.view_all']);
        $this->actingAs($viewer)->get(route('flats.show', $this->sale->flat))->assertOk()
            ->assertDontSee(route('sales.show', $this->sale), false)->assertDontSee('Contract Value');
    }

    public function test_customer_tabs_list_only_that_customers_sales_and_payments(): void
    {
        $admin = User::query()->where('email', 'admin@gmail.com')->firstOrFail();
        $customer = $this->sale->customer;
        $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk()
            ->assertSee(route('sales.table', ['customer' => $customer->public_id]), false)
            ->assertSee(route('customer-payments.table', ['customer' => $customer->public_id]), false)
            ->assertDontSee('Coming in Phase');

        $html = $this->actingAs($admin)->ajax()->get(route('customer-payments.table', ['customer' => $customer->public_id]))->assertOk()->json('data.html');
        $own = CustomerPayment::query()->where('customer_id', $customer->id)->pluck('payment_no');
        $others = CustomerPayment::query()->where('customer_id', '!=', $customer->id)->pluck('payment_no');
        $this->assertNotEmpty($own);
        $this->assertNotEmpty($others);
        $own->each(fn ($no) => $this->assertStringContainsString($no, $html));
        $others->each(fn ($no) => $this->assertStringNotContainsString($no, $html));
    }

    public function test_project_page_shows_actual_cost_only_with_cost_permission(): void
    {
        $admin = User::query()->where('email', 'admin@gmail.com')->firstOrFail();
        $this->actingAs($admin)->get(route('projects.show', $this->sale->project))->assertOk()
            ->assertSee('Actual cost')->assertSee(route('construction.cost', $this->sale->project), false)->assertDontSee('Phase 2');

        $this->actingAs($this->userWith(['projects.view_all']))->get(route('projects.show', $this->sale->project))->assertOk()->assertDontSee('Actual cost');
    }
}
