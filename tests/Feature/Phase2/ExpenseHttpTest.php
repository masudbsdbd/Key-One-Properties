<?php

namespace Tests\Feature\Phase2;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flat;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Models\User;
use Database\Factories\FlatFactory;
use Tests\TestCase;

class ExpenseHttpTest extends TestCase
{
    private const CLERK = ['projects.view', 'expenses.view.general', 'expenses.view.project', 'expenses.create.general', 'expenses.create.project', 'expenses.export'];

    private const MANAGER = ['projects.view', 'expenses.view.general', 'expenses.view.project', 'expenses.approve', 'expenses.pay', 'expenses.reverse'];

    private Project $project;

    private User $clerk;

    private User $manager;

    private ExpenseCategory $utilities;

    private ExpenseCategory $rent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create();
        $this->clerk = $this->userWith(self::CLERK);
        $this->manager = $this->userWith(self::MANAGER);
        $this->project->members()->attach([$this->clerk->id, $this->manager->id]);
        $this->utilities = ExpenseCategory::query()->create(['code' => 'UTIL', 'name' => 'Utilities', 'allowed_scope' => 'both']);
        $this->rent = ExpenseCategory::query()->create(['code' => 'RENT', 'name' => 'Office Rent', 'allowed_scope' => 'general']);
    }

    public function test_project_expense_lifecycle_and_report(): void
    {
        $this->actingAs($this->clerk)->ajax()->post(route('expenses.store'), [
            'scope' => 'project', 'category' => $this->utilities->public_id, 'expense_date' => now()->toDateString(),
            'description' => 'Site electricity', 'amount' => '2,500',
        ])->assertStatus(422)->assertJsonValidationErrors('project');
        $this->actingAs($this->clerk)->ajax()->post(route('expenses.store'), [
            'scope' => 'project', 'project' => $this->project->public_id, 'category' => $this->rent->public_id, 'expense_date' => now()->toDateString(),
            'description' => 'Site electricity', 'amount' => '2500',
        ])->assertStatus(422)->assertJsonValidationErrors('category');

        $this->actingAs($this->clerk)->ajax()->post(route('expenses.store'), [
            'scope' => 'project', 'project' => $this->project->public_id, 'category' => $this->utilities->public_id, 'expense_date' => now()->toDateString(),
            'description' => 'Site electricity', 'payee_name' => 'DESCO', 'amount' => '2,500', 'submit' => '1',
        ])->assertOk();
        $expense = Expense::query()->firstOrFail();
        $this->assertSame('submitted', $expense->status);

        $this->actingAs($this->clerk)->ajax()->post(route('expenses.approve', $expense))->assertForbidden();
        $this->actingAs($this->manager)->ajax()->post(route('expenses.approve', $expense))->assertOk();
        $this->actingAs($this->manager)->ajax()->post(route('expenses.pay', $expense), ['paid_on' => now()->toDateString(), 'payment_method' => 'bank'])
            ->assertStatus(422)->assertJsonValidationErrors('payment_reference');
        $this->actingAs($this->manager)->ajax()->post(route('expenses.pay', $expense), ['paid_on' => now()->toDateString(), 'payment_method' => 'cash'])->assertOk();
        $this->assertSame('paid', $expense->fresh()->status);
        $this->actingAs($this->clerk)->ajax()->put(route('expenses.update', $expense), [])->assertStatus(422);

        $html = $this->actingAs($this->clerk)->ajax()->get(route('expenses.table', ['project' => $this->project->public_id, 'status' => 'paid']))->assertOk()->json('data.html');
        $this->assertStringContainsString($expense->expense_no, $html);
        $this->assertStringContainsString('2,500.00', $html);
        $csv = $this->actingAs($this->clerk)->get(route('expenses.export', ['scope' => 'project']))->assertOk()->streamedContent();
        $this->assertStringContainsString($expense->expense_no, $csv);

        $this->actingAs($this->clerk)->get(route('expenses.show', $expense))->assertOk()->assertSee('DESCO');
        $this->actingAs($this->clerk)->get(route('expenses.index'))->assertOk();
        $this->actingAs($this->manager)->get(route('expenses.export'))->assertForbidden();
    }

    public function test_project_expense_tagged_to_a_unit_becomes_direct_unit_cost(): void
    {
        $flat = Flat::factory()->forFloor(FlatFactory::floorFor($this->project))->create();
        $foreign = Flat::factory()->forFloor(FlatFactory::floorFor(Project::factory()->create()))->create();
        $input = ['scope' => 'project', 'project' => $this->project->public_id, 'category' => $this->utilities->public_id, 'expense_date' => now()->toDateString(), 'description' => 'Tiles for 3B', 'amount' => '900', 'submit' => '1'];
        $this->assertSame($flat->public_id, $this->actingAs($this->clerk)->ajax()->get(route('options.expense-flats', ['project' => $this->project->public_id]))->json('results.0.id'));
        $this->actingAs($this->clerk)->ajax()->post(route('expenses.store'), $input + ['flat' => $foreign->public_id])->assertStatus(422)->assertJsonValidationErrors('flat');
        $this->actingAs($this->clerk)->ajax()->post(route('expenses.store'), $input + ['flat' => $flat->public_id])->assertOk();
        $expense = Expense::query()->firstOrFail();
        $this->actingAs($this->manager)->ajax()->post(route('expenses.approve', $expense))->assertOk();
        $this->assertSame($flat->id, ProjectCostAllocation::query()->where('source_id', $expense->id)->value('flat_id'));
        $this->actingAs($this->manager)->ajax()->post(route('expenses.reverse', $expense), ['reason' => 'Wrong unit'])->assertOk();
        $this->assertSame([$flat->id, $flat->id], ProjectCostAllocation::query()->where('source_id', $expense->id)->pluck('flat_id')->all());
    }

    public function test_visibility_by_scope_and_project(): void
    {
        $general = Expense::query()->create(['expense_no' => 'EXP-T-1', 'scope' => 'general', 'category_id' => $this->rent->id, 'expense_date' => now(), 'description' => 'Rent', 'amount' => '100', 'status' => 'draft', 'created_by' => $this->clerk->id]);
        $other = Project::factory()->create();
        $hidden = Expense::query()->create(['expense_no' => 'EXP-T-2', 'scope' => 'project', 'project_id' => $other->id, 'category_id' => $this->utilities->id, 'expense_date' => now(), 'description' => 'Other site', 'amount' => '100', 'status' => 'draft', 'created_by' => $this->clerk->id]);

        $projectOnly = $this->userWith(['projects.view', 'expenses.view.project']);
        $this->project->members()->attach($projectOnly->id);
        $this->actingAs($projectOnly)->get(route('expenses.show', $general))->assertForbidden();
        $this->actingAs($this->clerk)->get(route('expenses.show', $hidden))->assertForbidden();
        $html = $this->actingAs($this->clerk)->ajax()->get(route('expenses.table'))->assertOk()->json('data.html');
        $this->assertStringContainsString('EXP-T-1', $html);
        $this->assertStringNotContainsString('EXP-T-2', $html);

        $generalOnly = $this->userWith(['expenses.view.general', 'expenses.create.general']);
        $this->actingAs($generalOnly)->ajax()->post(route('expenses.store'), [
            'scope' => 'project', 'project' => $this->project->public_id, 'category' => $this->utilities->public_id, 'expense_date' => now()->toDateString(), 'description' => 'X', 'amount' => '1',
        ])->assertForbidden();
    }
}
