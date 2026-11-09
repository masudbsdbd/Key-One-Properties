<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Support\Dashboard;
use App\Support\Format;
use App\Support\Money;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SampleDataSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['keyone.super_admin.password' => 'Admin@12345']);
        $this->seed([SuperAdminSeeder::class, ExpenseCategorySeeder::class, SampleDataSeeder::class, SampleDataSeeder::class]);
        Project::factory()->create(['name' => 'Key One Green Court']);
        $this->admin = User::query()->where('email', 'admin@gmail.com')->firstOrFail();
        Cache::flush();
    }

    /** Money figures shown in a tile of a list page, keyed by tile label. */
    private function tiles(string $route, array $query = []): array
    {
        $html = (string) $this->actingAs($this->admin)->ajax()->get(route($route, $query))->assertOk()->json('data.stats');
        preg_match_all('/text-ink-500">([^<]+)<\/span>\s*<span[^>]*>([^<]+)<\/span>/', $html, $m);

        return array_combine(array_map('trim', $m[1]), array_map(fn ($v) => html_entity_decode(trim($v)), $m[2]));
    }

    private function money(string $value): string
    {
        return Format::money(Money::amount($value), false);
    }

    public function test_dashboard_figures_equal_the_list_tiles_they_link_to(): void
    {
        $dashboard = new Dashboard($this->admin);
        $kpis = collect($dashboard->get('kpis')['tiles'])->keyBy('label');
        $month = [now(config('app.display_timezone'))->startOfMonth()->toDateString(), now(config('app.display_timezone'))->endOfMonth()->toDateString()];

        $this->assertSame($this->tiles('customer-payments.table', ['from' => $month[0], 'to' => $month[1]])['Collected'], $this->money($kpis['Collected This Month']['value']));
        $this->assertStringContainsString($this->tiles('dues.table')['Overdue'], $kpis['Receivable']['note']);
        $this->assertSame($this->tiles('sales.table', ['from' => $month[0], 'to' => $month[1]])['Contract Value'], $this->money($kpis['Sales This Month']['value']));

        $payable = Money::add(
            str_replace(',', '', preg_replace('/[^\d.,]/u', '', $this->tiles('supplier-bills.table')['Unpaid'])),
            str_replace(',', '', preg_replace('/[^\d.,]/u', '', $this->tiles('contractor-bills.table')['Unpaid'])),
        );
        $this->assertSame($this->money($payable), $this->money($kpis['Payables']['value']));

        $overdue = Money::add('0', ...array_column($dashboard->get('attention')['overdue'], 'amount'));
        $this->assertSame($this->tiles('dues.table')['Overdue'], $this->money($overdue));
    }

    public function test_project_filter_and_permissions_shape_the_dashboard(): void
    {
        $project = Project::query()->orderBy('id')->firstOrFail();
        $other = Project::query()->where('id', '!=', $project->id)->firstOrFail();
        $rows = collect((new Dashboard($this->admin, $other))->get('projects')['rows']);
        $this->assertSame([$other->code], $rows->pluck('code')->all());
        $this->actingAs($this->admin)->ajax()->get(route('dashboard.panel', ['units', 'project' => $other->public_id]))->assertOk()->assertDontSee($project->code);

        $clerk = $this->userWith(['projects.view']);
        $other->members()->attach($clerk->id);
        $this->actingAs($clerk)->get(route('dashboard'))->assertOk()->assertSee('data-widget="projects"', false)->assertDontSee('data-widget="kpis"', false)->assertDontSee('data-widget="cashflow"', false);
        $this->actingAs($clerk)->ajax()->get(route('dashboard.panel', 'kpis'))->assertForbidden();
        $html = $this->actingAs($clerk)->ajax()->get(route('dashboard.panel', ['projects', 'project' => $project->public_id]))->assertOk()->json('data.html');
        $this->assertStringNotContainsString($project->code, $html);
    }

    public function test_new_reports_agree_with_the_list_tiles(): void
    {
        $csv = $this->actingAs($this->admin)->get(route('reports.export', ['key' => 'collection-forecast', 'format' => 'csv']))->assertOk()->streamedContent();
        $total = str_getcsv(trim(collect(explode("\n", trim($csv)))->last()));
        $this->assertSame($this->tiles('dues.table')['All Open Dues'], $this->money(end($total)));

        $csv = $this->actingAs($this->admin)->get(route('reports.export', ['key' => 'project-summary', 'format' => 'csv']))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', array_slice(explode("\n", trim($csv)), 1));
        $spent = Money::add('0', ...array_map(fn ($r) => $r[5], $rows));
        $this->assertSame($this->tiles('projects.table')['Spent'], $this->money($spent));
    }
}
