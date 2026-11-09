<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Applies whitelisted search, sort and pagination from table query parameters.
 */
class TableQuery
{
    public const PER_PAGE = 15;

    /**
     * @param  array<int, string>  $searchColumns  columns searched with LIKE (relation.column allowed)
     * @param  array<string, string|\Closure>  $sorts  public sort key => column or closure(Builder, dir)
     */
    public static function apply(
        Builder $query,
        Request $request,
        array $searchColumns = [],
        array $sorts = [],
        ?string $defaultSort = null,
        string $defaultDir = 'asc',
    ): LengthAwarePaginator {
        return self::paginate(self::prepare($query, $request, $searchColumns, $sorts, $defaultSort, $defaultDir), $request);
    }

    /** Search and sort without paginating; shared by lists and their exports. */
    public static function prepare(
        Builder $query,
        Request $request,
        array $searchColumns = [],
        array $sorts = [],
        ?string $defaultSort = null,
        string $defaultDir = 'asc',
    ): Builder {
        $term = trim((string) $request->input('search', ''));

        if ($term !== '' && $searchColumns) {
            $like = '%'.addcslashes(mb_substr($term, 0, 100), '%_\\').'%';
            $query->where(function (Builder $q) use ($searchColumns, $like) {
                foreach ($searchColumns as $column) {
                    if (str_contains($column, '.')) {
                        [$relation, $field] = explode('.', $column, 2);
                        $q->orWhereHas($relation, fn (Builder $r) => $r->where($field, 'like', $like));
                    } else {
                        $q->orWhere($column, 'like', $like);
                    }
                }
            });
        }

        $sortKey = (string) $request->input('sort', $defaultSort);
        $dir = $request->input('dir', $defaultDir) === 'desc' ? 'desc' : 'asc';

        if (isset($sorts[$sortKey])) {
            $sort = $sorts[$sortKey];
            $sort instanceof \Closure ? $sort($query, $dir) : $query->orderBy($sort, $dir);
        } elseif ($defaultSort && isset($sorts[$defaultSort])) {
            $query->orderBy($sorts[$defaultSort], $defaultDir);
        }

        $query->orderBy($query->getModel()->getQualifiedKeyName(), 'desc');

        return $query;
    }

    public static function paginate(Builder $query, Request $request): LengthAwarePaginator
    {
        $perPage = min(max((int) $request->input('per_page', self::PER_PAGE), 5), 100);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Select2 search helper: returns [results, more].
     *
     * @param  callable(Model): array{id: string, text: string}  $map
     * @return array{0: array<int, array{id: string, text: string}>, 1: bool}
     */
    public static function options(Builder $query, Request $request, array $searchColumns, callable $map, int $perPage = 25): array
    {
        $term = trim((string) $request->input('q', ''));

        // Restoring a filter after a reload: look up one record by public id inside the same scoped query.
        if (is_string($selected = $request->input('selected')) && $selected !== '') {
            $item = $query->where($query->getModel()->qualifyColumn('public_id'), $selected)->first();

            return [$item ? [$map($item)] : [], false];
        }

        if ($term !== '') {
            $like = '%'.addcslashes(mb_substr($term, 0, 100), '%_\\').'%';
            $query->where(fn (Builder $q) => collect($searchColumns)->each(fn ($c) => $q->orWhere($c, 'like', $like)));
        }

        $page = max(1, (int) $request->input('page', 1));
        $items = $query->forPage($page, $perPage + 1)->get();

        return [$items->take($perPage)->map($map)->values()->all(), $items->count() > $perPage];
    }

    /** Restricts a list to the from/to dates of the request (YYYY-MM-DD; anything else is ignored). */
    public static function period(Builder $query, Request $request, string $column): Builder
    {
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            if ($date = self::date($request->input($key))) {
                $query->whereDate($column, $op, $date);
            }
        }

        return $query;
    }

    /** "2026-11-01 to 2026-11-30" for export headers, or null when no period is set. */
    public static function periodLabel(Request $request): ?string
    {
        [$from, $to] = [self::date($request->input('from')), self::date($request->input('to'))];

        return $from || $to ? ($from ?? '…').' to '.($to ?? '…') : null;
    }

    private static function date(mixed $value): ?string
    {
        $value = (string) $value;

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : null;
    }
}
