<?php

namespace App\Modules\Reports\Reports;

use App\Models\Expense;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ExpenseSummaryReport extends BaseReport
{
    public function key(): string
    {
        return 'expense-summary';
    }

    public function name(): string
    {
        return 'General vs Project Expenses';
    }

    public function group(): string
    {
        return 'expense';
    }

    public function basis(): string
    {
        return 'Approved and paid expenses; general expenses are not charged to projects';
    }

    public function filters(): array
    {
        return ['project', 'period', 'scope_expense'];
    }

    public function columns(): array
    {
        return ['Scope' => 'text', 'Project' => 'text', 'Category' => 'text', 'Count' => 'number', 'Approved (unpaid)' => 'money', 'Paid' => 'money', 'Total' => 'money'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = Expense::query()->visibleTo($user)->whereIn('expenses.status', ['approved', 'paid'])
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')->leftJoin('projects', 'projects.id', '=', 'expenses.project_id')
            ->selectRaw("expenses.scope, projects.code AS project_code, expense_categories.name AS category_name, COUNT(*) AS n,
                SUM(CASE WHEN expenses.status = 'approved' THEN expenses.amount ELSE 0 END) AS approved_amount,
                SUM(CASE WHEN expenses.status = 'paid' THEN expenses.amount ELSE 0 END) AS paid_amount")
            ->groupBy('expenses.scope', 'projects.code', 'expense_categories.name');
        if ($project = self::project($request, $user)) {
            $query->where('expenses.project_id', $project->id);
        }
        if (array_key_exists($s = (string) $request->input('scope'), Expense::SCOPES)) {
            $query->where('expenses.scope', $s);
        }

        return $this->inPeriod($query, $request, 'expenses.expense_date')->orderBy('expenses.scope')->orderBy('projects.code')->orderBy('expense_categories.name');
    }

    public function row(mixed $r): array
    {
        return [Expense::SCOPES[$r->scope] ?? $r->scope, $r->project_code ?? '—', $r->category_name, (int) $r->n, Money::amount((string) $r->approved_amount), Money::amount((string) $r->paid_amount),
            Money::amount(Money::add((string) $r->approved_amount, (string) $r->paid_amount))];
    }
}
