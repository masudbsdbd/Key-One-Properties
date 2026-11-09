<?php

namespace Tests\Feature\Phase2;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    public function test_project_page_shows_phase_two_tabs_by_permission(): void
    {
        $project = Project::factory()->create();
        $full = $this->userWith(['projects.view', 'construction.view', 'construction.cost.view', 'materials.view', 'purchases.view', 'contractors.view', 'expenses.view.project']);
        $basic = $this->userWith(['projects.view']);
        $project->members()->attach([$full->id, $basic->id]);

        $html = $this->actingAs($full)->get(route('projects.show', $project))->assertOk()->getContent();
        foreach (['tab-construction', 'tab-materials', 'tab-purchases', 'tab-contractors', 'tab-expenses'] as $tab) {
            $this->assertStringContainsString($tab, $html);
        }

        $html = $this->actingAs($basic)->get(route('projects.show', $project))->assertOk()->getContent();
        foreach (['tab-construction', 'tab-materials', 'tab-purchases', 'tab-contractors', 'tab-expenses'] as $tab) {
            $this->assertStringNotContainsString($tab, $html);
        }
    }

    public function test_dashboard_lists_only_the_approvals_a_user_can_act_on(): void
    {
        $project = Project::factory()->create();
        $category = ExpenseCategory::query()->create(['code' => 'X', 'name' => 'Misc']);
        $clerk = $this->userWith(['projects.view', 'expenses.view.project', 'expenses.create.project']);
        $approver = $this->userWith(['projects.view', 'expenses.view.project', 'expenses.approve']);
        $outsider = $this->userWith(['projects.view', 'expenses.view.project', 'expenses.approve']);
        $project->members()->attach([$clerk->id, $approver->id]);
        Expense::query()->create(['expense_no' => 'EXP-T-9', 'scope' => 'project', 'project_id' => $project->id, 'category_id' => $category->id,
            'expense_date' => now(), 'description' => 'Fuel', 'amount' => '10', 'status' => 'submitted', 'created_by' => $clerk->id]);

        $this->actingAs($approver)->get(route('dashboard'))->assertOk()->assertSee('Awaiting You')->assertSee('Expenses');
        $this->actingAs($outsider)->get(route('dashboard'))->assertOk()->assertDontSee('Awaiting You');
        $this->actingAs($clerk)->get(route('dashboard'))->assertOk()->assertDontSee('Awaiting You');
    }
}
