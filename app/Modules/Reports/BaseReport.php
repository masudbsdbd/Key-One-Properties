<?php

namespace App\Modules\Reports;

use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * One operational report: its permission, basis, filters, columns and a scoped query. The same class feeds
 * the screen (paginated), the immediate export and the queued export, so they always agree.
 */
abstract class BaseReport
{
    public const GROUPS = [
        'project' => 'Projects', 'construction' => 'Construction', 'inventory' => 'Inventory', 'purchase' => 'Purchasing',
        'contractor' => 'Contractors', 'expense' => 'Expenses', 'sales' => 'Sales', 'collections' => 'Collections',
        'investors' => 'Investors', 'audit' => 'Audit',
    ];

    abstract public function key(): string;

    abstract public function name(): string;

    abstract public function group(): string;

    /** Posted, Approved, Current snapshot, ... */
    abstract public function basis(): string;

    /** @return array<string, string> label => text|money|qty|date|number */
    abstract public function columns(): array;

    abstract public function query(Request $request, User $user): Builder;

    /** @return array<int, mixed> values in column order */
    abstract public function row(mixed $record): array;

    public function permission(): string
    {
        return 'reports.'.$this->group();
    }

    /** @return array<int, string> filters the page shows: project, period, status, search and report specific ones */
    public function filters(): array
    {
        return ['project', 'period'];
    }

    /** @return array<string, string> options for the status filter */
    public function statuses(): array
    {
        return [];
    }

    /** Search columns used by the table search box. */
    public function search(): array
    {
        return [];
    }

    /** @return array<int, mixed>|null totals in column order */
    public function totals(Builder $query): ?array
    {
        return null;
    }

    public function defaultSort(): string
    {
        return '';
    }

    public function allowed(User $user): bool
    {
        return $user->can('reports.view') && $user->can($this->permission());
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    public static function period(Request $request): array
    {
        $parse = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? Carbon::parse($v) : null;

        return [$parse($request->input('from')), $parse($request->input('to'))];
    }

    public static function project(Request $request, User $user): ?Project
    {
        return $request->filled('project') ? Project::query()->visibleTo($user)->where('public_id', (string) $request->input('project'))->first() : null;
    }

    /** Applies the period to a date column. */
    protected function inPeriod(Builder $query, Request $request, string $column): Builder
    {
        [$from, $to] = self::period($request);

        return $query->when($from, fn ($q) => $q->whereDate($column, '>=', $from->toDateString()))->when($to, fn ($q) => $q->whereDate($column, '<=', $to->toDateString()));
    }

    /** @return array<string, string> filter summary for exports and the page header */
    public function describe(Request $request, User $user): array
    {
        [$from, $to] = self::period($request);

        return array_filter([
            'Basis' => $this->basis(),
            'Project' => self::project($request, $user)?->name,
            'Period' => $from || $to ? ($from?->format('d M Y') ?? '…').' – '.($to?->format('d M Y') ?? '…') : null,
            'Status' => $this->statuses()[(string) $request->input('status')] ?? null,
            'Currency' => 'BDT',
        ]);
    }
}
