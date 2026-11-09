<?php

namespace Tests\Feature\Phase4;

use App\Jobs\GenerateReportExport;
use App\Models\CustomerPayment;
use App\Models\Flat;
use App\Models\Investor;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Models\ReportRun;
use App\Models\User;
use App\Modules\Investors\Services\InvestorLedger;
use App\Modules\Reports\ExecutiveMetrics;
use App\Modules\Reports\ReportRegistry;
use App\Support\Money;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SampleDataSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Storage;
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

    public function test_queued_exports_are_private_revalidated_and_expire(): void
    {
        Storage::fake('local');
        $project = Project::query()->orderBy('id')->firstOrFail();
        $clerk = $this->userWith(['projects.view_all', 'reports.view', 'reports.collections', 'reports.export']);

        $this->actingAs($clerk)->ajax()->get(route('reports.queue.form', ['key' => 'collection-receipts', 'project' => $project->public_id]))->assertOk()->assertSee($project->name);
        $this->actingAs($clerk)->ajax()->post(route('reports.queue', 'collection-receipts'), ['format' => 'xls'])->assertStatus(422);
        $this->actingAs($clerk)->ajax()->post(route('reports.queue', 'collection-receipts'), ['format' => 'csv', 'project' => $project->public_id, 'from' => '2026-01-01'])->assertOk();
        $run = ReportRun::query()->latest('id')->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertSame(CustomerPayment::query()->whereIn('status', ['posted', 'reversed'])->where('project_id', $project->id)->count(), $run->row_count);
        Storage::disk('local')->assertExists($run->file->storage_path);

        $this->flushHeaders();
        $this->actingAs($clerk)->get(route('report-exports.index'))->assertOk();
        $this->assertStringContainsString('Ready', $this->actingAs($clerk)->ajax()->get(route('report-exports.table'))->json('data.html'));
        $this->flushHeaders();
        $this->actingAs($clerk)->get(route('report-exports.download', $run))->assertOk();
        $this->actingAs($this->userWith(['reports.view', 'reports.collections', 'reports.export']))->get(route('report-exports.download', $run))->assertNotFound();
        $this->assertTrue(Activity::query()->where('event', 'report_downloaded')->exists());

        $clerk->revokePermissionTo('reports.collections');
        $this->actingAs($clerk->fresh())->get(route('report-exports.download', $run))->assertForbidden();
        $clerk->givePermissionTo('reports.collections');

        $this->travel(8)->days();
        $this->actingAs($clerk->fresh())->get(route('report-exports.download', $run))->assertNotFound();
        $this->artisan('keyone:prune-report-exports')->assertSuccessful();
        $this->assertSame('expired', $run->fresh()->status);
        Storage::disk('local')->assertMissing($run->file->storage_path);

        // Permission lost before the job runs: the export fails without a file.
        $queued = ReportRun::query()->create(['report_key' => 'collection-receipts', 'requested_by' => $clerk->id, 'filters_json' => [], 'filter_hash' => 'x', 'format' => 'csv', 'status' => 'queued']);
        $clerk->revokePermissionTo('reports.export');
        (new GenerateReportExport($queued->id))->handle();
        $this->assertSame('failed', $queued->fresh()->status);
        $this->assertNull($queued->fresh()->file);
        $this->actingAs($this->userWith(['reports.view']))->ajax()->post(route('reports.queue', 'collection-receipts'), ['format' => 'csv'])->assertForbidden();
    }
}
