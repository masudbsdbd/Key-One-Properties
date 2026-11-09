<?php

namespace Tests\Feature;

use App\Models\CustomerPayment;
use App\Models\FlatSale;
use App\Models\Project;
use App\Models\User;
use App\Support\Format;
use App\Support\Money;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SampleDataSeeder;
use Database\Seeders\SuperAdminSeeder;
use Tests\TestCase;

class ListSummaryTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['keyone.super_admin.password' => 'Admin@12345']);
        $this->seed([SuperAdminSeeder::class, ExpenseCategorySeeder::class, SampleDataSeeder::class, SampleDataSeeder::class]);
        $this->admin = User::query()->where('email', 'admin@gmail.com')->firstOrFail();
    }

    private function stats(string $route, array $query = []): string
    {
        return (string) $this->actingAs($this->admin)->ajax()->get(route($route, $query))->assertOk()->json('data.stats');
    }

    private function money(mixed $value): string
    {
        return e(Format::money(Money::amount((string) $value), false));
    }

    public function test_tiles_equal_the_list_totals_and_ignore_their_own_status_filter(): void
    {
        $value = FlatSale::query()->whereIn('status', FlatSale::ACTIVE)->sum('net_contract_value');
        $html = $this->stats('sales.table');
        $this->assertStringContainsString($this->money($value), $html);

        $posted = CustomerPayment::query()->where('status', 'posted')->sum('amount');
        $pending = CustomerPayment::query()->where('status', 'pending')->sum('amount');
        $this->assertGreaterThan(0, $pending);
        foreach ([[], ['status' => 'pending']] as $query) {
            $html = $this->stats('customer-payments.table', $query);
            $this->assertStringContainsString($this->money($posted), $html);
            $this->assertStringContainsString($this->money($pending), $html);
        }
        $this->assertStringContainsString('aria-pressed="true"', $this->stats('customer-payments.table', ['status' => 'pending']));
    }

    public function test_period_filter_narrows_rows_and_tiles(): void
    {
        $from = now()->subDays(10)->toDateString();
        $inPeriod = CustomerPayment::query()->where('status', 'posted')->whereDate('payment_date', '>=', $from)->sum('amount');
        $all = CustomerPayment::query()->where('status', 'posted')->sum('amount');
        $this->assertNotEquals((string) $inPeriod, (string) $all);

        $response = $this->actingAs($this->admin)->ajax()->get(route('customer-payments.table', ['from' => $from]))->assertOk();
        $this->assertStringContainsString($this->money($inPeriod), $response->json('data.stats'));
        $this->assertSame(CustomerPayment::query()->whereDate('payment_date', '>=', $from)->count(), $response->json('meta.total'));
        $this->actingAs($this->admin)->ajax()->get(route('customer-payments.table', ['from' => "2026-01-01' OR 1=1 --"]))->assertOk()
            ->assertJsonPath('meta.total', CustomerPayment::query()->count());
    }

    public function test_exports_end_with_a_total_row(): void
    {
        $csv = $this->actingAs($this->admin)->get(route('sales.export', ['format' => 'csv']))->assertOk()->streamedContent();
        $last = str_getcsv(trim(collect(explode("\n", trim($csv)))->last()));
        $this->assertContains('Total', $last);
        $this->assertContains(Money::amount((string) FlatSale::query()->sum('net_contract_value')), $last);

        foreach (['cancellations.export', 'late-fees.export', 'construction.export', 'borrows.export', 'transfers.export', 'adjustments.export', 'materials.export', 'warehouses.export'] as $route) {
            $this->actingAs($this->admin)->get(route($route, ['format' => 'csv']))->assertOk();
        }
    }

    public function test_every_list_with_tiles_renders_them(): void
    {
        foreach (['sales.table', 'bookings.table', 'refunds.table', 'cancellations.table', 'customer-payments.table', 'installment-plans.table', 'dues.table', 'late-fees.assessments',
            'suppliers.table', 'purchase-orders.table', 'supplier-bills.table', 'supplier-payments.table', 'contractors.table', 'work-orders.table', 'contractor-bills.table', 'contractor-payments.table',
            'investors.table', 'investor-agreements.table', 'investor-capital.table', 'profit-calculations.table', 'distributions.table',
            'projects.table', 'customers.table', 'expenses.table', 'labour.table', 'construction.table', 'stock.movements.table', 'borrows.table', 'transfers.table', 'adjustments.table'] as $route) {
            $this->assertStringContainsString('summary-tile', $this->stats($route), $route);
        }
    }

    public function test_option_lookup_by_selected_id_stays_inside_the_users_scope(): void
    {
        [$mine, $other] = Project::query()->orderBy('id')->take(2)->get()->all();
        $member = $this->userWith(['projects.view']);
        $mine->members()->attach($member->id);

        $this->actingAs($member)->ajax()->get(route('options.projects', ['selected' => $mine->public_id]))->assertOk()->assertJsonPath('results.0.id', $mine->public_id);
        $this->actingAs($member)->ajax()->get(route('options.projects', ['selected' => $other->public_id]))->assertOk()->assertJsonCount(0, 'results');
    }
}
