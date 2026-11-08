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

    public function test_labour_total_is_computed_on_server_and_cost_posted_once(): void
    {
        $this->actingAs($this->engineer)->ajax()->post(route('labour.store'), [
            'project' => $this->project->public_id, 'work_date' => now()->toDateString(), 'labour_type' => 'mason',
            'headcount' => 12, 'units' => '1.5', 'rate' => '850', 'total_amount' => '1',
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
            'project' => $this->project->public_id, 'work_date' => now()->toDateString(), 'labour_type' => 'helper', 'headcount' => 1, 'units' => 1, 'rate' => 500,
        ]);
        $entry = LabourEntry::query()->firstOrFail();

        $this->actingAs($outsider)->get(route('construction.show', $this->project))->assertForbidden();
        $this->actingAs($outsider)->ajax()->get(route('construction.work-packages.index', $this->project))->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('labour.approve', $entry))->assertForbidden();
        $this->actingAs($outsider)->ajax()->get(route('construction.cost', $this->project))->assertForbidden();
        $this->actingAs($outsider)->get(route('construction.cost.export', $this->project))->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('labour.store'), [
            'project' => $this->project->public_id, 'work_date' => now()->toDateString(), 'labour_type' => 'helper', 'headcount' => 1, 'units' => 1, 'rate' => 500,
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
            'labour_type' => 'helper', 'headcount' => 1, 'units' => 1, 'rate' => 500,
        ])->assertOk();

        $this->actingAs($this->engineer)->ajax()->delete(route('construction.work-packages.destroy', [$this->project, $package]))->assertStatus(409);
    }
}
