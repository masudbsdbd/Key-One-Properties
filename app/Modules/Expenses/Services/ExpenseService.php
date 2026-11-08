<?php

namespace App\Modules\Expenses\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use App\Support\Costing\CostAttribution;
use App\Support\DocumentNumber;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Draft → submitted → approved → paid. Approval is the obligation (and project cost for project expenses);
 * payment is settlement only. General expenses never reach project cost.
 */
class ExpenseService
{
    public function __construct(private CostAttribution $costs, private FinancialEventRecorder $events) {}

    /**
     * @param  array{scope: string, expense_date: string, description: string, payee_name?: ?string, amount: string}  $data
     */
    public function save(?Expense $expense, ExpenseCategory $category, ?Project $project, ?WorkPackage $package, array $data, User $user): Expense
    {
        return DB::transaction(function () use ($expense, $category, $project, $package, $data, $user) {
            $scope = $data['scope'];
            if ($scope === 'project' && ! $project) {
                throw ValidationException::withMessages(['project' => 'Select the project for a project expense.']);
            }
            if ($scope === 'general') {
                $project = null;
                $package = null;
            }
            if (! $category->is_active || ! $category->allows($scope)) {
                throw ValidationException::withMessages(['category' => 'This category cannot be used for '.mb_strtolower(Expense::SCOPES[$scope]).' expenses.']);
            }
            if ($package && $package->project_id !== $project?->id) {
                throw ValidationException::withMessages(['work_package' => 'Select a work package of this project.']);
            }
            if (! Money::isPositive($data['amount'])) {
                throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
            }

            if ($expense) {
                $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
                Approval::ensureStatus($expense->status, Expense::EDITABLE, 'Only draft or rejected expenses can be edited.');
            } else {
                $expense = new Expense(['expense_no' => DocumentNumber::next('EXP'), 'status' => 'draft', 'created_by' => $user->id]);
            }

            $expense->fill([
                'scope' => $scope,
                'project_id' => $project?->id,
                'work_package_id' => $package?->id,
                'category_id' => $category->id,
                'expense_date' => $data['expense_date'],
                'description' => $data['description'],
                'payee_name' => $data['payee_name'] ?? null,
                'amount' => Money::amount($data['amount']),
            ])->save();
            $this->log($expense, $user, $expense->wasRecentlyCreated ? 'created' : 'updated');

            return $expense;
        });
    }

    public function submit(Expense $expense, User $user): void
    {
        DB::transaction(function () use ($expense, $user) {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            Approval::ensureStatus($expense->status, Expense::EDITABLE, 'This expense was already submitted.');
            $expense->forceFill(['status' => 'submitted', 'submitted_by' => $user->id, 'submitted_at' => now(), 'status_reason' => null])->save();
            $this->log($expense, $user, 'submitted');
        });
    }

    public function approve(Expense $expense, User $user): void
    {
        DB::transaction(function () use ($expense, $user) {
            $expense = Expense::query()->with(['project', 'category'])->lockForUpdate()->findOrFail($expense->id);
            Approval::ensureStatus($expense->status, 'submitted', 'Only submitted expenses can be approved.');
            Approval::ensureNotSelf($user, $expense->created_by, 'an expense');

            $expense->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
            if ($expense->scope === 'project') {
                $this->costs->post($expense->project, $expense, 'expense', $expense->amount, $expense->expense_date, $expense->work_package_id);
            }
            $this->events->record('expenses', $expense, 'expense.approved', $expense->amount, $expense->expense_date,
                ['expense_no' => $expense->expense_no, 'scope' => $expense->scope, 'category' => $expense->category->code, 'source_module' => 'expenses'],
                project: $expense->project, user: $user);
            $this->log($expense, $user, 'approved');
        });
    }

    public function reject(Expense $expense, string $reason, User $user): void
    {
        DB::transaction(function () use ($expense, $reason, $user) {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            Approval::ensureStatus($expense->status, 'submitted', 'Only submitted expenses can be rejected.');
            $expense->forceFill(['status' => 'rejected', 'status_reason' => $reason])->save();
            $this->log($expense, $user, 'rejected', $reason);
        });
    }

    /**
     * @param  array{paid_on: string, payment_method: string, payment_reference?: ?string}  $data
     */
    public function pay(Expense $expense, array $data, User $user): void
    {
        DB::transaction(function () use ($expense, $data, $user) {
            $expense = Expense::query()->with('project')->lockForUpdate()->findOrFail($expense->id);
            Approval::ensureStatus($expense->status, 'approved', 'Only approved, unpaid expenses can be paid.');
            if ($expense->expense_date->gt($data['paid_on'])) {
                throw ValidationException::withMessages(['paid_on' => 'Payment cannot be before the expense date.']);
            }

            $expense->forceFill([
                'status' => 'paid', 'paid_by' => $user->id, 'paid_at' => now(),
                'paid_on' => $data['paid_on'], 'payment_method' => $data['payment_method'], 'payment_reference' => $data['payment_reference'] ?? null,
            ])->save();
            $this->events->record('expenses', $expense, 'expense.paid', $expense->amount, $expense->paid_on,
                ['expense_no' => $expense->expense_no, 'scope' => $expense->scope, 'method' => $expense->payment_method, 'source_module' => 'expenses'],
                project: $expense->project, user: $user);
            $this->log($expense, $user, 'paid', null, ['method' => $expense->payment_method, 'reference' => $expense->payment_reference]);
        });
    }

    public function reverse(Expense $expense, string $reason, User $user): void
    {
        DB::transaction(function () use ($expense, $reason, $user) {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            Approval::ensureStatus($expense->status, ['approved', 'paid'], 'Only approved or paid expenses can be reversed.');

            $expense->forceFill(['status' => 'reversed', 'reversed_by' => $user->id, 'reversed_at' => now(), 'status_reason' => $reason])->save();
            $this->costs->reverse($expense, now(config('app.display_timezone')));
            $this->events->reverse('expenses', $expense, now(config('app.display_timezone')), $user);
            $this->log($expense, $user, 'reversed', $reason);
        });
    }

    private function log(Expense $expense, User $user, string $action, ?string $reason = null, array $metadata = []): void
    {
        $expense->actions()->create(['actor_id' => $user->id, 'action' => $action, 'reason' => $reason, 'metadata' => $metadata ?: null, 'created_at' => now()]);
    }
}
