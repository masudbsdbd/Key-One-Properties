<?php

namespace App\Modules\Construction\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ContractorBill;
use App\Models\Expense;
use App\Models\LabourEntry;
use App\Models\LabourType;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Models\StockIssue;
use App\Models\StockIssueReturn;
use App\Models\WorkPackage;
use App\Support\Ajax;
use App\Support\Costing\CostAttribution;
use App\Support\Export\Report;
use App\Support\Format;
use App\Support\ListSummary;
use App\Support\Money;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CostController extends Controller
{
    /** Document number column of each cost source, used by the search box. */
    private const NUMBERS = [Expense::class => 'expense_no', StockIssue::class => 'issue_no', StockIssueReturn::class => 'return_no', LabourEntry::class => 'entry_no', ContractorBill::class => 'bill_no'];

    public function table(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('construction:cost-view', $project);
        $rows = TableQuery::paginate($this->listing($request, $project), $request);

        return Ajax::table(view('construction.cost._rows', ['rows' => $rows, 'types' => LabourType::names()])->render(), $rows,
            ['stats' => $this->summary($request, $project)->render()]);
    }

    public function export(Request $request, Project $project): Response
    {
        Gate::authorize('construction:cost-export', $project);
        $types = LabourType::names();

        return Report::make('Project Cost')->sumMoney()
            ->meta(['Project' => $project->name.' ('.$project->code.')', 'Category' => ProjectCostAllocation::CATEGORIES[(string) $request->input('category')] ?? null,
                'Work Package' => WorkPackage::query()->whereBelongsTo($project)->where('public_id', (string) $request->input('work_package'))->value('name'),
                'Period' => TableQuery::periodLabel($request)])
            ->columns(['Date' => 'date', 'Category' => 'text', 'Source' => 'text', 'Details' => 'text', 'Work Package' => 'text', 'Reversal' => 'text', 'Payment' => 'text', 'Amount' => 'money'])
            ->rows(fn () => $this->listing($request, $project)->lazy()->map(fn (ProjectCostAllocation $row) => [
                $row->effective_at, ProjectCostAllocation::CATEGORIES[$row->cost_category] ?? $row->cost_category,
                $row->sourceLabel(), $row->sourceDetail($types), $row->workPackage?->code, $row->reversal_of_id ? 'Yes' : '', $row->payment()['label'] ?? '', $row->amount,
            ]))
            ->respond($request);
    }

    private function summary(Request $request, Project $project): ListSummary
    {
        $sums = ['total' => 'SUM(project_cost_allocations.amount)'];
        foreach (array_keys(ProjectCostAllocation::CATEGORIES) as $key) {
            $sums[$key] = ['SUM(CASE WHEN project_cost_allocations.cost_category = ? THEN project_cost_allocations.amount ELSE 0 END)', [$key]];
        }
        $row = ListSummary::aggregate($this->listing(ListSummary::without($request, ['category']), $project), $sums);
        $icons = ['material' => 'layers', 'labour' => 'users', 'contractor' => 'user', 'expense' => 'note'];

        $due = CostAttribution::unpaid($project, $this->listing(ListSummary::without($request, ['category']), $project));
        $note = fn (string $amount) => Money::isPositive($amount) ? Format::money($amount).' unpaid' : null;

        $summary = ListSummary::make($request)->money('Total Cost', $row->total, 'bar-chart', 'accent', ['category' => ''], $note(Money::add(...array_values($due))));
        foreach (ProjectCostAllocation::CATEGORIES as $key => $label) {
            $summary->money($label, $row->{$key}, $icons[$key] ?? 'tag', 'info', ['category' => $key], $note($due[$key] ?? '0'));
        }

        return $summary;
    }

    private function listing(Request $request, Project $project): Builder
    {
        $query = ProjectCostAllocation::query()->where('project_cost_allocations.project_id', $project->id)->with([
            'workPackage:id,code,name',
            'source' => fn (MorphTo $source) => $source->morphWith([
                StockIssue::class => ['warehouse.project', 'lines.material:id,name'], StockIssueReturn::class => ['issue.warehouse.project'], LabourEntry::class => ['project'], ContractorBill::class => ['contractor:id,name', 'payments:id,bill_id,amount,status'],
            ]),
        ]);
        if (array_key_exists($category = (string) $request->input('category'), ProjectCostAllocation::CATEGORIES)) {
            $query->where('cost_category', $category);
        }
        if ($package = WorkPackage::query()->whereBelongsTo($project)->where('public_id', (string) $request->input('work_package'))->first()) {
            $query->where('work_package_id', $package->id);
        }
        if (in_array($payment = (string) $request->input('payment'), ['unpaid', 'paid'], true)) {
            $query->whereNull('reversal_of_id')->whereHasMorph('source', [ContractorBill::class, Expense::class],
                fn (Builder $q) => $q->where('status', $payment === 'paid' ? 'paid' : 'approved'));
        }
        $term = trim((string) $request->input('search', ''));
        if ($term !== '') {
            $like = '%'.addcslashes(mb_substr($term, 0, 100), '%_\\').'%';
            $query->whereHasMorph('source', array_keys(self::NUMBERS), fn (Builder $q, string $type) => $q->where(self::NUMBERS[$type], 'like', $like));
        }
        TableQuery::period($query, $request, 'effective_at');

        return TableQuery::prepare($query, $request, [], ['date' => 'effective_at', 'amount' => 'amount'], 'date', 'desc');
    }
}
