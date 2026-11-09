<?php

namespace Tests\Feature\Phase4;

use App\Models\CustomerPayment;
use App\Models\Flat;
use App\Models\Investor;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Models\User;
use App\Modules\Investors\Services\InvestorLedger;
use App\Modules\Reports\ExecutiveMetrics;
use App\Modules\Reports\ReportRegistry;
use App\Support\Money;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SampleDataSeeder;
use Database\Seeders\SuperAdminSeeder;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['keyone.super_admin.password' => 'Admin@12345']);
        $this->seed([SuperAdminSeeder::class, ExpenseCategorySeeder::class, SampleDataSeeder::class, SampleDataSeeder::class]);
        $this->admin = User::query()->where('email', 'admin@gmail.com')->firstOrFail();
    }

    /** @return array<int, array> */
    private function rows(User $user, string $key, array $filters = []): array
    {
        $this->flushHeaders();
        $csv = $this->actingAs($user)->get(route('reports.export', ['key' => $key, 'format' => 'csv'] + $filters))->assertOk()->streamedContent();

        return array_map('str_getcsv', array_values(array_filter(explode("\n", trim($csv)))));
    }

    public function test_every_report_runs_and_reconciles_with_its_source(): void
    {
        foreach (array_keys(ReportRegistry::all()) as $key) {
            $this->flushHeaders();
            $this->actingAs($this->admin)->get(route('reports.show', $key))->assertOk();
            $this->actingAs($this->admin)->ajax()->get(route('reports.table', $key))->assertOk();
        }
        $this->flushHeaders();
        $this->actingAs($this->admin)->get(route('reports.index'))->assertOk()->assertSee('Supplier Payable Aging');

        // Cost ledger total equals the posted cost of the project.
        $project = Project::query()->orderBy('id')->firstOrFail();
        $this->flushHeaders();
        $csv = $this->actingAs($this->admin)->get(route('reports.export', ['key' => 'project-cost', 'format' => 'csv', 'project' => $project->public_id]))->streamedContent();
        $this->assertStringContainsString(Money::amount((string) ProjectCostAllocation::query()->where('project_id', $project->id)->sum('amount')), str_replace(',', '', $csv));

        // Collected total excludes pending payments.
        $collected = Money::amount((string) CustomerPayment::query()->where('status', 'posted')->sum('amount'));
        $this->flushHeaders();
        $csv = $this->actingAs($this->admin)->get(route('reports.export', ['key' => 'collection-receipts', 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString($collected, str_replace(',', '', $csv));
        $this->assertTrue(Activity::query()->where('event', 'report_exported')->exists());

        // Investor position matches the ledger balances.
        $investor = Investor::query()->where('name', 'Mahbub Alam')->firstOrFail();
        $b = InvestorLedger::balances($investor);
        $csv = $this->actingAs($this->admin)->get(route('reports.export', ['key' => 'investor-position', 'format' => 'csv', 'investor' => $investor->public_id]))->streamedContent();
        $this->assertStringContainsString($b['capital'], str_replace(',', '', $csv));

        // Unit counts match the inventory.
        $csv = $this->actingAs($this->admin)->get(route('reports.export', ['key' => 'unit-status', 'format' => 'csv', 'project' => $project->public_id]))->streamedContent();
        $this->assertStringContainsString((string) Flat::query()->where('project_id', $project->id)->count(), $csv);
    }

    public function test_reports_and_widgets_follow_permissions_and_project_scope(): void
    {
        $project = Project::query()->orderBy('id')->firstOrFail();
        $other = Project::query()->where('id', '!=', $project->id)->firstOrFail();
        $manager = $this->userWith(['projects.view', 'reports.view', 'reports.construction', 'reports.export', 'dashboard.executive.view', 'construction.view']);
        $other->members()->attach($manager->id);

        $this->actingAs($manager)->get(route('reports.index'))->assertOk()->assertSee('Project Cost Ledger')->assertDontSee('Supplier Payable Aging');
        $this->actingAs($manager)->get(route('reports.show', 'supplier-aging'))->assertForbidden();
        $this->actingAs($manager)->get(route('reports.show', 'no-such-report'))->assertNotFound();
        $html = $this->actingAs($manager)->ajax()->get(route('reports.table', 'project-cost'))->json('data.html');
        $this->assertStringNotContainsString($project->code, $html);
        $this->flushHeaders();
        $this->actingAs($this->userWith(['reports.view', 'reports.construction']))->get(route('reports.export', ['key' => 'project-cost', 'format' => 'csv']))->assertForbidden();

        foreach (array_keys(ExecutiveMetrics::WIDGETS) as $w) {
            $this->actingAs($this->admin)->ajax()->get(route('executive.widget', $w))->assertOk();
        }
        $this->actingAs($manager)->ajax()->get(route('executive.widget', 'construction'))->assertOk();
        $this->actingAs($manager)->ajax()->get(route('executive.widget', 'investors'))->assertForbidden();
        $this->actingAs($manager)->ajax()->get(route('executive.widget', 'nothing'))->assertNotFound();
        $this->flushHeaders();
        $this->actingAs($manager)->get(route('executive.index'))->assertOk()->assertSee('Construction Cost')->assertDontSee('data-widget="investors"', false);
        $this->actingAs($this->userWith(['reports.view']))->get(route('executive.index'))->assertForbidden();

        // General expenses never inflate project cost.
        $budget = $this->rows($this->admin, 'project-budget', ['project' => $project->public_id]);
        $actual = (float) str_replace(',', '', $budget[1][array_search('Actual', $budget[0], true)]);
        $this->assertEqualsWithDelta((float) ProjectCostAllocation::query()->where('project_id', $project->id)->sum('amount'), $actual, 0.001);
    }
}
