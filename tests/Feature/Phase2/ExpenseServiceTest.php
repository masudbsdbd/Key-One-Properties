<?php

namespace Tests\Feature\Phase2;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialEvent;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Models\User;
use App\Modules\Expenses\Services\ExpenseService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class ExpenseServiceTest extends TestCase
{
    private ExpenseService $service;

    private User $clerk;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ExpenseService::class);
        $this->clerk = User::factory()->create();
        $this->manager = User::factory()->create();
        $this->project = Project::factory()->create();
    }

    private function category(string $scope = 'both'): ExpenseCategory
    {
        return ExpenseCategory::query()->create(['code' => strtoupper(substr($scope, 0, 3)).strtoupper(Str::random(6)), 'name' => 'Cat '.$scope, 'allowed_scope' => $scope]);
    }

    private function expense(string $scope, ?Project $project, string $amount = '1500'): Expense
    {
        return $this->service->save(null, $this->category(), $project, null,
            ['scope' => $scope, 'expense_date' => now()->toDateString(), 'description' => 'Site electricity bill', 'amount' => $amount], $this->clerk);
    }

    public function test_general_expense_has_no_project_and_never_reaches_project_cost(): void
    {
        $expense = $this->expense('general', $this->project);
        $this->assertNull($expense->project_id);

        $this->service->submit($expense, $this->clerk);
        $this->service->approve($expense, $this->manager);
        $this->assertSame(0, ProjectCostAllocation::query()->count());
        $this->assertSame(1, FinancialEvent::query()->where('event_type', 'expense.approved')->whereNull('project_id')->count());
    }

    public function test_project_expense_requires_a_project_and_posts_cost_once(): void
    {
        try {
            $this->expense('project', null);
            $this->fail('Project expense without project.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('project', $e->errors());
        }

        $expense = $this->expense('project', $this->project);
        $this->service->submit($expense, $this->clerk);
        try {
            $this->service->approve($expense, $this->clerk);
            $this->fail('Self-approved.');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(AssertionFailedError::class, $e);
        }
        $this->service->approve($expense, $this->manager);
        $this->service->pay($expense, ['paid_on' => now()->toDateString(), 'payment_method' => 'cash'], $this->manager);

        $this->assertSame('1500.00', (string) ProjectCostAllocation::query()->where('cost_category', 'expense')->sum('amount'));
        $this->assertSame('paid', $expense->fresh()->status);

        $this->expectException(ConflictHttpException::class);
        $this->service->pay($expense, ['paid_on' => now()->toDateString(), 'payment_method' => 'cash'], $this->manager);
    }

    public function test_category_scope_is_enforced_and_reversal_restores_cost(): void
    {
        try {
            $this->service->save(null, $this->category('general'), $this->project, null,
                ['scope' => 'project', 'expense_date' => now()->toDateString(), 'description' => 'X', 'amount' => '10'], $this->clerk);
            $this->fail('General-only category used for a project expense.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('category', $e->errors());
        }

        $expense = $this->expense('project', $this->project, '800');
        $this->service->submit($expense, $this->clerk);
        $this->service->reject($expense, 'Missing receipt', $this->manager);
        $this->service->save($expense->fresh(), $expense->category, $this->project, null,
            ['scope' => 'project', 'expense_date' => now()->toDateString(), 'description' => 'With receipt', 'amount' => '750'], $this->clerk);
        $this->service->submit($expense, $this->clerk);
        $this->service->approve($expense, $this->manager);
        $this->service->reverse($expense, 'Duplicate', $this->manager);

        $this->assertSame('0.00', (string) ProjectCostAllocation::query()->sum('amount'));
        $this->assertSame('reversed', $expense->fresh()->status);
        $this->assertSame(['created', 'submitted', 'rejected', 'updated', 'submitted', 'approved', 'reversed'], $expense->actions()->pluck('action')->all());
    }
}
