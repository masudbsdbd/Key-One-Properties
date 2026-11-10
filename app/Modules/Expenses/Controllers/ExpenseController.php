<?php

namespace App\Modules\Expenses\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flat;
use App\Models\Project;
use App\Models\WorkPackage;
use App\Modules\Expenses\Services\ExpenseService;
use App\Support\Ajax;
use App\Support\Export\Documents;
use App\Support\Export\Report;
use App\Support\ListSummary;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->canAny(['expenses.view.general', 'expenses.view.project']), 403);

        return view('expenses.index');
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['expenses.view.general', 'expenses.view.project']), 403);
        $query = $this->filtered($request)->with(['project:id,public_id,code,name', 'category:id,code,name,parent_id', 'category.parent:id,name']);

        $expenses = TableQuery::apply($query, $request, ['expense_no', 'description', 'payee_name'], ['date' => 'expense_date', 'amount' => 'amount'], 'date', 'desc');

        $row = ListSummary::aggregate($this->filtered(ListSummary::without($request)), [
            'approved' => "SUM(CASE WHEN expenses.status IN ('approved', 'paid') THEN expenses.amount ELSE 0 END)",
            'paid' => "SUM(CASE WHEN expenses.status = 'paid' THEN expenses.amount ELSE 0 END)",
            'unpaid' => "SUM(CASE WHEN expenses.status = 'approved' THEN expenses.amount ELSE 0 END)",
            'pending' => "SUM(CASE WHEN expenses.status = 'submitted' THEN expenses.amount ELSE 0 END)",
            'pending_n' => "SUM(CASE WHEN expenses.status = 'submitted' THEN 1 ELSE 0 END)",
        ]);
        $summary = ListSummary::make($request)
            ->money('Approved Spend', $row->approved, 'note', 'info')
            ->money('Paid', $row->paid, 'check', 'success', ['status' => 'paid'])
            ->money('Approved, Unpaid', $row->unpaid, 'clock', 'warning', ['status' => 'approved'])
            ->money('Awaiting Approval', $row->pending, 'alert', 'accent', ['status' => 'submitted'], ListSummary::many($row->pending_n, 'expense'));

        return Ajax::table(view('expenses._rows', ['expenses' => $expenses, 'inProject' => $request->boolean('in_project')])->render(), $expenses, ['stats' => $summary->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('expenses.export') && $request->user()->canAny(['expenses.view.general', 'expenses.view.project']), 403);
        $query = $this->filtered($request)->with(['project:id,code', 'category:id,code,name'])->orderBy('expense_date')->orderBy('id');
        $total = (string) (clone $this->filtered($request))->whereIn('status', ['approved', 'paid'])->sum('amount');

        return Report::make('Expenses')->sumMoney()
            ->meta($this->filterSummary($request))
            ->columns(['Expense No' => 'text', 'Date' => 'date', 'Project' => 'text', 'Category' => 'text', 'Description' => 'text', 'Payee' => 'text', 'Amount' => 'money', 'Status' => 'text'])
            ->rows(fn () => $query->lazy()->map(fn (Expense $e) => [$e->expense_no, $e->expense_date, $e->project?->code ?? 'General', $e->category->name,
                $e->description, $e->payee_name, $e->amount, Expense::STATUSES[$e->status]]))
            ->totals([null, null, null, null, null, 'Approved and paid', $total, null])
            ->respond($request);
    }

    /** @return array<string, string> */
    public function document(Request $request, Expense $expense): Response
    {
        abort_unless($expense->isVisibleTo($request->user()), 403);

        return Documents::expense($expense)->respond($request);
    }

    private function filterSummary(Request $request): array
    {
        return [
            'Status' => Expense::STATUSES[(string) $request->input('status')] ?? null,
            'Scope' => Expense::SCOPES[(string) $request->input('scope')] ?? null,
            'Project' => Project::findByPublicId($request->input('project'))?->name,
            'Category' => ExpenseCategory::findByPublicId($request->input('category'))?->name,
            'Period' => $request->filled('from') || $request->filled('to') ? ($request->input('from') ?: '…').' to '.($request->input('to') ?: '…') : null,
        ];
    }

    /** Units of a visible project, for tagging a project expense to one unit. */
    public function flatOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('expenses.create.project'), 403);
        $project = Project::query()->visibleTo($request->user())->where('public_id', (string) $request->input('project'))->first();
        $query = Flat::query()->where('project_id', $project?->id ?? 0)->with('building:id,code')->orderBy('flat_no');
        [$results, $more] = TableQuery::options($query, $request, ['flat_no'], fn (Flat $f) => ['id' => $f->public_id, 'text' => $f->building->code.' · Unit '.$f->flat_no]);

        return Ajax::options($results, $more);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['expenses.create.general', 'expenses.create.project']), 403);
        $project = Project::query()->visibleTo($request->user())->where('public_id', (string) $request->input('project'))->first();
        $expense = new Expense(['scope' => $project || ! $request->user()->can('expenses.create.general') ? 'project' : 'general']);
        $expense->setRelation('project', $project);

        return $this->form($expense, 'New Expense');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['expenses.create.general', 'expenses.create.project']), 403);
        [$category, $project, $package, $data] = $this->input($request);
        $expense = $this->service->save(null, $category, $project, $package, $data, $request->user());
        if ($request->boolean('submit')) {
            $this->service->submit($expense, $request->user());
        }

        return Ajax::success('Expense '.$expense->expense_no.' saved.', ['redirect' => route('expenses.show', $expense)]);
    }

    public function edit(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeEdit($request, $expense);

        return $this->form($expense->load(['project', 'workPackage', 'flat.building', 'category.parent']), 'Edit '.$expense->expense_no);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeEdit($request, $expense);
        [$category, $project, $package, $data] = $this->input($request);
        $this->service->save($expense, $category, $project, $package, $data, $request->user());

        return Ajax::success('Expense updated.');
    }

    public function show(Request $request, Expense $expense): View
    {
        abort_unless($expense->isVisibleTo($request->user()), 403);
        $expense->load(['project', 'workPackage', 'flat', 'category.parent', 'creator', 'actions.actor']);

        return view('expenses.show', ['expense' => $expense]);
    }

    public function submit(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeEdit($request, $expense);
        $this->service->submit($expense, $request->user());

        return Ajax::success('Submitted for approval.');
    }

    public function approve(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeAction($request, $expense, 'expenses.approve');
        $this->service->approve($expense, $request->user());

        return Ajax::success('Expense approved.');
    }

    public function reasonForm(Request $request, Expense $expense, string $action): JsonResponse
    {
        $this->authorizeAction($request, $expense, $action === 'reject' ? 'expenses.approve' : 'expenses.reverse');

        return Ajax::success(null, ['title' => Str::headline($action).' '.$expense->expense_no, 'html' => view('partials.reason-form', [
            'action' => route('expenses.'.$action, $expense), 'button' => Str::headline($action), 'reload' => true,
        ])->render()]);
    }

    public function reject(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeAction($request, $expense, 'expenses.approve');
        $this->service->reject($expense, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Expense rejected.');
    }

    public function reverse(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeAction($request, $expense, 'expenses.reverse');
        $this->service->reverse($expense, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Expense reversed.');
    }

    public function payForm(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeAction($request, $expense, 'expenses.pay');

        return Ajax::success(null, ['title' => 'Pay '.$expense->expense_no, 'html' => view('expenses._pay', ['expense' => $expense])->render()]);
    }

    public function pay(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeAction($request, $expense, 'expenses.pay');
        $data = $request->validate([
            'paid_on' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'payment_method' => ['required', Rule::in(array_keys(Expense::METHODS))],
            'payment_reference' => [Rule::requiredIf(in_array($request->input('payment_method'), ['bank', 'cheque', 'mobile'], true)), 'nullable', 'string', 'max:80'],
        ], ['payment_reference.required' => 'Enter the transaction or cheque reference.']);
        $this->service->pay($expense, $data, $request->user());

        return Ajax::success('Payment recorded.');
    }

    private function filtered(Request $request): Builder
    {
        $query = Expense::query()->visibleTo($request->user());
        if (array_key_exists($status = (string) $request->input('status'), Expense::STATUSES)) {
            $query->where('status', $status);
        }
        if (array_key_exists($scope = (string) $request->input('scope'), Expense::SCOPES)) {
            $query->where('scope', $scope);
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }
        if ($category = ExpenseCategory::findByPublicId($request->input('category'))) {
            $query->where(fn ($q) => $q->where('category_id', $category->id)->orWhereIn('category_id', ExpenseCategory::query()->where('parent_id', $category->id)->select('id')));
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            $value = (string) $request->input($key);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value)) {
                $query->whereDate('expense_date', $op, $value);
            }
        }

        return $query;
    }

    /**
     * @return array{0: ExpenseCategory, 1: ?Project, 2: ?WorkPackage, 3: array}
     */
    private function input(Request $request): array
    {
        $request->merge(['amount' => str_replace(',', '', (string) $request->input('amount'))]);
        $data = $request->validate([
            'scope' => ['required', Rule::in(array_keys(Expense::SCOPES))],
            'project' => ['nullable', 'required_if:scope,project', 'string', 'size:16'],
            'work_package' => ['nullable', 'string', 'size:16'],
            'flat' => ['nullable', 'string', 'size:16'],
            'category' => ['required', 'string', 'size:16'],
            'expense_date' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'description' => ['required', 'string', 'max:255'],
            'payee_name' => ['nullable', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
        ], ['project.required_if' => 'Select the project for a project expense.']);
        abort_unless($request->user()->can('expenses.create.'.$data['scope']), 403);

        $category = ExpenseCategory::query()->where('public_id', $data['category'])->first()
            ?? throw ValidationException::withMessages(['category' => 'Select a category.']);
        $project = null;
        $package = null;
        if ($data['scope'] === 'project') {
            $project = Project::query()->visibleTo($request->user())->where('public_id', $data['project'])->first()
                ?? throw ValidationException::withMessages(['project' => 'Select a project you can access.']);
            if (! empty($data['work_package'])) {
                $package = WorkPackage::query()->where('project_id', $project->id)->where('public_id', $data['work_package'])->first()
                    ?? throw ValidationException::withMessages(['work_package' => 'Select a work package of this project.']);
            }
            if (! empty($data['flat'])) {
                $data['flat_id'] = Flat::query()->where('project_id', $project->id)->where('public_id', $data['flat'])->value('id')
                    ?? throw ValidationException::withMessages(['flat' => 'Select a unit of this project.']);
            }
        }

        return [$category, $project, $package, $data];
    }

    private function authorizeEdit(Request $request, Expense $expense): void
    {
        abort_unless($expense->isVisibleTo($request->user()) && $expense->canBeCreatedBy($request->user()), 403);
    }

    private function authorizeAction(Request $request, Expense $expense, string $permission): void
    {
        abort_unless($request->user()->can($permission) && $expense->isVisibleTo($request->user()), 403);
    }

    private function form(Expense $expense, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('expenses._form', ['expense' => $expense])->render()]);
    }
}
