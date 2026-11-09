<?php

namespace Tests\Feature;

use App\Models\CustomerPayment;
use App\Models\FlatSale;
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

        foreach (['cancellations.export', 'late-fees.export'] as $route) {
            $this->actingAs($this->admin)->get(route($route, ['format' => 'csv']))->assertOk();
        }
    }
}
