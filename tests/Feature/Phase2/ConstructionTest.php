<?php

namespace Tests\Feature\Phase2;

use App\Models\ConstructionProgressUpdate;
use App\Models\LabourEntry;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use App\Support\Costing\CostAttribution;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConstructionTest extends TestCase
{
    private const ALL = [
        'projects.view', 'construction.view', 'construction.manage', 'construction.progress.submit', 'construction.progress.approve',
        'construction.labour.manage', 'construction.labour.approve', 'construction.cost.view', 'construction.cost.export',
    ];

    private Project $project;

    private User $engineer;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create();
        $this->engineer = $this->member(self::ALL);
        $this->manager = $this->member(self::ALL);
    }

    private function member(array $permissions): User
    {
        $user = $this->userWith($permissions);
        $this->project->members()->attach($user->id);

        return $user;
    }

    private function package(): WorkPackage
    {
        $this->actingAs($this->engineer)->ajax()->post(route('construction.work-packages.store', $this->project), [
            'code' => 'wp-01', 'name' => 'Piling', 'budget_amount' => '1,000,000', 'status' => 'not_started',
        ])->assertOk();

        return WorkPackage::query()->firstOrFail();
    }

    public function test_work_package_code_is_unique_per_project(): void
    {
        $this->package();
        $this->actingAs($this->engineer)->ajax()->post(route('construction.work-packages.store', $this->project), [
            'code' => 'WP-01', 'name' => 'Other', 'budget_amount' => '0', 'status' => 'not_started',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_progress_approval_updates_package_and_blocks_self_approval(): void
    {
        $package = $this->package();
        $this->actingAs($this->engineer)->ajax()->post(route('construction.progress.store', $this->project), [
            'work_package' => $package->public_id, 'progress_percent' => 40, 'report_date' => now()->toDateString(),
            'notes' => 'Piling 40%', 'visibility' => 'investor',
        ])->assertOk();
        $update = ConstructionProgressUpdate::query()->firstOrFail();

        $this->actingAs($this->engineer)->ajax()->post(route('construction.progress.approve', [$this->project, $update]))
            ->assertStatus(422)->assertJsonValidationErrors('approval');

        $this->actingAs($this->manager)->ajax()->post(route('construction.progress.approve', [$this->project, $update]))->assertOk();
        $this->actingAs($this->manager)->ajax()->post(route('construction.progress.approve', [$this->project, $update]))->assertStatus(409);

        $this->assertSame('40.00', $package->fresh()->progress_percent);
        $this->assertSame('in_progress', $package->fresh()->status);
        $this->assertNotNull($update->fresh()->published_at);
    }

    public function test_progress_validation_and_rejection(): void
    {
        $package = $this->package();
        $this->actingAs($this->engineer)->ajax()->post(route('construction.progress.store', $this->project), [
            'work_package' => $package->public_id, 'progress_percent' => 120, 'report_date' => now()->addDay()->toDateString(), 'notes' => '', 'visibility' => 'x',
        ])->assertStatus(422)->assertJsonValidationErrors(['progress_percent', 'report_date', 'notes', 'visibility']);

        $this->actingAs($this->engineer)->ajax()->post(route('construction.progress.store', $this->project), [
            'work_package' => $package->public_id, 'progress_percent' => 10, 'report_date' => now()->toDateString(), 'notes' => 'x', 'visibility' => 'internal',
        ])->assertOk();
        $update = ConstructionProgressUpdate::query()->firstOrFail();

        $this->actingAs($this->manager)->ajax()->post(route('construction.progress.reject', [$this->project, $update]), [])->assertStatus(422);
        $this->actingAs($this->manager)->ajax()->post(route('construction.progress.reject', [$this->project, $update]), ['reason' => 'Photos missing'])->assertOk();
        $this->assertSame('rejected', $update->fresh()->status);
        $this->assertSame('0.00', $package->fresh()->progress_percent);
    }

    public function test_investor_view_contains_only_safe_published_fields(): void
    {
        $package = $this->package();
        foreach (['investor', 'internal'] as $visibility) {
            $this->actingAs($this->engineer)->ajax()->post(route('construction.progress.store', $this->project), [
                'work_package' => $package->public_id, 'progress_percent' => 20, 'report_date' => now()->toDateString(), 'notes' => $visibility, 'visibility' => $visibility,
            ]);
        }
        ConstructionProgressUpdate::query()->each(fn ($u) => $this->actingAs($this->manager)->ajax()->post(route('construction.progress.approve', [$this->project, $u])));

        $visible = ConstructionProgressUpdate::query()->investorVisible()->get();
        $this->assertCount(1, $visible);
        $payload = $visible->first()->toInvestorArray();
        $this->assertSame(['id', 'work_package', 'milestone', 'progress_percent', 'report_date', 'notes', 'published_at'], array_keys($payload));
        $this->assertStringNotContainsString('budget', json_encode($payload));
    }

    public function test_work_package_list_shows_spent_against_budget(): void
    {
        $package = $this->package();
        $package->forceFill(['budget_amount' => '10000'])->save();
        $this->actingAs($this->engineer)->ajax()->post(route('labour.store'), [
            'project' => $this->project->public_id, 'work_package' => $package->public_id, 'work_date' => now()->toDateString(), 'rows' => [['type' => 'mason', 'headcount' => 15, 'units' => 1, 'rate' => '800']],
        ])->assertOk();
        $this->actingAs($this->manager)->ajax()->post(route('labour.approve', LabourEntry::query()->firstOrFail()))->assertOk();

        $html = $this->actingAs($this->manager)->ajax()->get(route('construction.work-packages.index', $this->project))->assertOk()->json('data.html');
        $this->assertStringContainsString('12,000', $html);
        $this->assertStringContainsString('2,000 over', $html);
        $viewer = $this->userWith(['projects.view', 'construction.view']);
        $this->project->members()->attach($viewer->id);
        $html = $this->actingAs($viewer)->ajax()->get(route('construction.work-packages.index', $this->project))->assertOk()->json('data.html');
        $this->assertStringContainsString('Piling', $html);
        $this->assertStringNotContainsString('Spent', $html);
    }

    public function test_a_daily_sheet_records_one_entry_per_labour_row(): void
    {
        $package = $this->package();
        $sheet = ['project' => $this->project->public_id, 'work_package' => $package->public_id, 'work_date' => now()->toDateString(), 'notes' => 'Roof slab'];

        $this->actingAs($this->engineer)->ajax()->post(route('labour.store'), [...$sheet, 'rows' => [
            0 => ['type' => 'mason', 'headcount' => 4, 'units' => 1, 'rate' => '900'],
            1 => ['type' => '', 'headcount' => '', 'units' => '1', 'rate' => ''],
            2 => ['type' => 'helper', 'headcount' => 6, 'units' => 1, 'rate' => ''],
        ]])->assertStatus(422)->assertJsonValidationErrors('rows.2.rate')->assertJsonMissingValidationErrors(['rows.1.type', 'rows.0.type']);
        $this->actingAs($this->engineer)->ajax()->post(route('labour.store'), [...$sheet, 'rows' => [1 => ['type' => '', 'headcount' => '', 'units' => '1', 'rate' => '']]])
            ->assertStatus(422)->assertJsonPath('errors.rows.0', 'Add at least one labour row.');
        $this->assertSame(0, LabourEntry::query()->count());

        $this->actingAs($this->engineer)->ajax()->post(route('labour.store'), [...$sheet, 'rows' => [
            ['type' => 'mason', 'headcount' => 4, 'units' => 1, 'rate' => '900'],
            ['type' => 'helper', 'headcount' => 6, 'units' => 1, 'rate' => '600'],
            ['type' => 'rod_binder', 'headcount' => 2, 'units' => '0.5', 'rate' => '1,000'],
        ]])->assertOk()->assertJsonPath('message', fn ($m) => str_starts_with($m, '3 labour entries recorded'));

        $entries = LabourEntry::query()->orderBy('id')->get();
        $this->assertSame(['mason', 'helper', 'rod_binder'], $entries->pluck('labour_type')->all());
        $this->assertSame(['3600.00', '3600.00', '1000.00'], $entries->pluck('total_amount')->all());
        $this->assertSame([$package->id], $entries->pluck('work_package_id')->unique()->values()->all());
        $this->assertSame(['Roof slab'], $entries->pluck('notes')->unique()->values()->all());
    }

    public function test_approvers_approve_many_labour_entries_at_once(): void
    {
        $sheet = fn (User $by, array $rows) => $this->actingAs($by)->ajax()->post(route('labour.store'), [
            'project' => $this->project->public_id, 'work_date' => now()->toDateString(), 'rows' => $rows,
        ])->assertOk();
        $sheet($this->engineer, [['type' => 'mason', 'headcount' => 4, 'units' => 1, 'rate' => '900'], ['type' => 'helper', 'headcount' => 6, 'units' => 1, 'rate' => '600']]);
        $sheet($this->manager, [['type' => 'helper', 'headcount' => 1, 'units' => 1, 'rate' => '600']]);
        $ids = LabourEntry::query()->pluck('public_id')->all();

        $this->assertStringContainsString('data-select-row', $this->actingAs($this->manager)->ajax()->get(route('labour.table'))->json('data.html'));
        $this->actingAs($this->userWith(['construction.view']))->ajax()->post(route('labour.bulk-approve'), ['ids' => $ids])->assertForbidden();

        $this->actingAs($this->manager)->ajax()->post(route('labour.bulk-approve'), ['ids' => $ids])->assertOk()
            ->assertJsonPath('message', '2 entries approved. Skipped: 1 recorded by you.');
        $this->assertSame('7200.00', CostAttribution::totals($this->project)['labour']);

        $this->actingAs($this->manager)->ajax()->post(route('labour.bulk-approve'), ['ids' => $ids])->assertOk()
            ->assertJsonPath('message', '0 entries approved. Skipped: 1 recorded by you, 2 not awaiting approval.');
        $this->actingAs($this->engineer)->ajax()->post(route('labour.bulk-approve'), ['all' => 1, 'status' => 'submitted'])->assertOk()
            ->assertJsonPath('message', '1 entry approved.');
        $this->assertSame('7800.00', CostAttribution::totals($this->project)['labour']);
        $this->assertSame(0, LabourEntry::query()->where('status', 'submitted')->count());
    }

    public function test_labour_total_is_computed_on_server_and_cost_posted_once(): void
    {
        $this->actingAs($this->engineer)->ajax()->post(route('labour.store'), [
            'project' => $this->project->public_id, 'work_date' => now()->toDateString(), 'rows' => [['type' => 'mason', 'headcount' => 12, 'units' => '1.5', 'rate' => '850']], 'total_amount' => '1',
        ])->assertOk();
        $entry = LabourEntry::query()->firstOrFail();
        $this->assertSame('15300.00', $entry->total_amount);
        $this->assertMatchesRegularExpression('/^LAB-\d{4}-\d{5}$/', $entry->entry_no);

        $this->actingAs($this->engineer)->ajax()->post(route('labour.approve', $entry))->assertStatus(422);
        $this->actingAs($this->manager)->ajax()->post(route('labour.approve', $entry))->assertOk();
        $this->actingAs($this->manager)->ajax()->post(route('labour.approve', $entry))->assertStatus(409);

        $this->assertSame('15300.00', CostAttribution::totals($this->project)['labour']);
        $this->assertDatabaseHas('financial_events', ['event_type' => 'labour.approved', 'amount' => '15300.00']);

        $this->actingAs($this->manager)->ajax()->post(route('labour.reverse', $entry), ['reason' => 'Wrong project'])->assertOk();
        $this->assertSame('0.00', CostAttribution::totals($this->project)['labour']);
        $this->assertSame('reversed', $entry->fresh()->status);
        $this->assertDatabaseHas('financial_events', ['event_type' => 'labour.approved', 'amount' => '-15300.00']);
    }

    public function test_out_of_scope_users_cannot_reach_construction_endpoints(): void
    {
        $outsider = $this->userWith(self::ALL);
        $package = $this->package();
        $this->actingAs($this->engineer)->ajax()->post(route('labour.store'), [
            'project' => $this->project->public_id, 'work_date' => now()->toDateString(), 'rows' => [['type' => 'helper', 'headcount' => 1, 'units' => 1, 'rate' => 500]],
        ]);
        $entry = LabourEntry::query()->firstOrFail();

        $this->actingAs($outsider)->get(route('construction.show', $this->project))->assertForbidden();
        $this->actingAs($this->engineer)->get(route('construction.show', $this->project))->assertRedirect(route('projects.show', $this->project).'#packages');
        $this->actingAs($this->engineer)->get(route('projects.show', $this->project))->assertOk()
            ->assertSee('id="tab-packages"', false)->assertSee('id="tab-labour"', false)->assertSee(route('construction.milestones.index', $this->project), false);
        $this->actingAs($outsider)->ajax()->get(route('construction.work-packages.index', $this->project))->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('labour.approve', $entry))->assertForbidden();
        $this->actingAs($outsider)->ajax()->get(route('construction.cost', $this->project))->assertForbidden();
        $this->actingAs($outsider)->get(route('construction.cost.export', $this->project))->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('labour.store'), [
            'project' => $this->project->public_id, 'work_date' => now()->toDateString(), 'rows' => [['type' => 'helper', 'headcount' => 1, 'units' => 1, 'rate' => 500]],
        ])->assertStatus(422)->assertJsonValidationErrors('project');
        $this->assertSame(0, count($this->actingAs($outsider)->ajax()->get(route('options.work-packages', ['project' => $this->project->public_id]))->json('results')));
        $this->assertStringNotContainsString($entry->entry_no, $this->actingAs($outsider)->ajax()->get(route('labour.table'))->json('data.html'));
        $this->assertNotNull($package);
    }

    public function test_progress_evidence_is_private_and_scoped(): void
    {
        Storage::fake('local');
        $package = $this->package();
        $this->actingAs($this->engineer)->ajax()->post(route('construction.progress.store', $this->project), [
            'work_package' => $package->public_id, 'progress_percent' => 5, 'report_date' => now()->toDateString(), 'notes' => 'x', 'visibility' => 'internal',
            'evidence' => [UploadedFile::fake()->image('site.jpg')],
        ])->assertOk();
        $file = ConstructionProgressUpdate::query()->firstOrFail()->attachments()->firstOrFail();

        $this->actingAs($this->manager)->get(route('attachments.show', $file))->assertOk();
        $this->actingAs($this->userWith(self::ALL))->get(route('attachments.show', $file))->assertForbidden();
    }

    public function test_used_work_package_cannot_be_deleted(): void
    {
        $package = $this->package();
        $this->actingAs($this->engineer)->ajax()->post(route('labour.store'), [
            'project' => $this->project->public_id, 'work_package' => $package->public_id, 'work_date' => now()->toDateString(),
            'rows' => [['type' => 'helper', 'headcount' => 1, 'units' => 1, 'rate' => 500]],
        ])->assertOk();

        $this->actingAs($this->engineer)->ajax()->delete(route('construction.work-packages.destroy', [$this->project, $package]))->assertStatus(409);
    }
}
