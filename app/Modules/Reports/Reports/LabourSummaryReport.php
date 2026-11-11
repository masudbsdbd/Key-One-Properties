<?php

namespace App\Modules\Reports\Reports;

use App\Models\LabourEntry;
use App\Models\LabourType;
use App\Models\Project;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Worker-days and labour cost of each project by type of labour. */
class LabourSummaryReport extends BaseReport
{
    public function key(): string
    {
        return 'labour-summary';
    }

    public function name(): string
    {
        return 'Labour Summary';
    }

    public function group(): string
    {
        return 'construction';
    }

    public function basis(): string
    {
        return 'Approved labour entries by work date; a worker-day is one worker for one day';
    }

    public function columns(): array
    {
        return ['Project' => 'text', 'Labour' => 'text', 'Entries' => 'number', 'Worker-Days' => 'number', 'Cost' => 'money', 'Avg per Worker-Day' => 'money'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = LabourEntry::query()->join('projects', 'projects.id', '=', 'labour_entries.project_id')->where('labour_entries.status', 'approved')
            ->whereIn('labour_entries.project_id', Project::query()->visibleTo($user)->select('projects.id'))
            ->groupBy('projects.code', 'labour_entries.labour_type')
            ->selectRaw('projects.code AS project_code, labour_entries.labour_type, COUNT(*) AS entries, SUM(labour_entries.headcount * labour_entries.units) AS worker_days, SUM(labour_entries.total_amount) AS cost');
        if ($project = self::project($request, $user)) {
            $query->where('labour_entries.project_id', $project->id);
        }

        return $this->inPeriod($query, $request, 'labour_entries.work_date')->orderBy('projects.code')->orderByDesc('cost');
    }

    public function row(mixed $r): array
    {
        static $types;
        $types ??= LabourType::names();

        return [$r->project_code, $types[$r->labour_type] ?? $r->labour_type, (int) $r->entries, (float) $r->worker_days, Money::amount((string) $r->cost),
            (float) $r->worker_days > 0 ? Money::amount((string) ((float) $r->cost / (float) $r->worker_days)) : null];
    }

    public function totals(Builder $query): ?array
    {
        $rows = (clone $query)->reorder()->get();

        return ['Total', null, (int) $rows->sum('entries'), (float) $rows->sum('worker_days'), Money::amount(Money::add('0', ...$rows->map(fn ($r) => (string) $r->cost)->all())), null];
    }
}
