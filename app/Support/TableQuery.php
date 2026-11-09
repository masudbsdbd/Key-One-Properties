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

        if ($term !== '') {
            $like = '%'.addcslashes(mb_substr($term, 0, 100), '%_\\').'%';
            $query->where(fn (Builder $q) => collect($searchColumns)->each(fn ($c) => $q->orWhere($c, 'like', $like)));
        }

        $page = max(1, (int) $request->input('page', 1));
        $items = $query->forPage($page, $perPage + 1)->get();

        return [$items->take($perPage)->map($map)->values()->all(), $items->count() > $perPage];
    }
}
