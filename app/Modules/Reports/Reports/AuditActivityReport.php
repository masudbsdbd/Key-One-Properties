<?php

namespace App\Modules\Reports\Reports;

use App\Models\User;
use App\Modules\Reports\BaseReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AuditActivityReport extends BaseReport
{
    public function key(): string
    {
        return 'audit-activity';
    }

    public function name(): string
    {
        return 'Activity and Approvals';
    }

    public function group(): string
    {
        return 'audit';
    }

    public function basis(): string
    {
        return 'Activity log';
    }

    public function filters(): array
    {
        return ['period', 'module', 'actor'];
    }

    public function search(): array
    {
        return ['description', 'event'];
    }

    public function columns(): array
    {
        return ['When' => 'text', 'Actor' => 'text', 'Module' => 'text', 'Action' => 'text', 'Record' => 'text'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = Activity::query()->with('causer:id,name');
        if ($module = (string) $request->input('module')) {
            $query->where('log_name', $module);
        }
        if ($actor = User::findByPublicId($request->input('actor'))) {
            $query->where('causer_type', $actor->getMorphClass())->where('causer_id', $actor->id);
        }

        return $this->inPeriod($query, $request, 'created_at')->latest('id');
    }

    public function row(mixed $a): array
    {
        return [$a->created_at->timezone(config('app.display_timezone'))->format('d M Y H:i'), $a->causer?->name ?? 'System', $a->log_name, str_replace('_', ' ', (string) ($a->event ?: $a->description)),
            $a->subject_type ? class_basename($a->subject_type) : '—'];
    }
}
