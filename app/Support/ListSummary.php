<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Summary tiles above a list. Built from the list's own filtered query, so every figure follows the
 * filters on screen. A tile with a preset applies those filters when clicked (and clears them again).
 */
final class ListSummary
{
    private array $tiles = [];

    public function __construct(private ?Request $request = null) {}

    public static function make(?Request $request = null): self
    {
        return new self($request ?? request());
    }

    /**
     * One row of aggregates from a filtered list query, without its ordering, paging or selected columns.
     *
     * Values go in as bindings: an expression is either SQL text or [SQL with ? placeholders, bindings].
     *
     * @param  array<string, string|array{0: string, 1: array<int, mixed>}>  $expressions  alias => aggregate
     */
    public static function aggregate(Builder $query, array $expressions): object
    {
        $base = $query->toBase()->cloneWithout(['columns', 'orders', 'limit', 'offset'])->cloneWithoutBindings(['select', 'order']);
        foreach ($expressions as $alias => $expression) {
            [$sql, $bindings] = is_array($expression) ? $expression : [$expression, []];
            $base->selectRaw($sql.' AS '.$base->getGrammar()->wrap($alias), $bindings);
        }

        return $base->first() ?? (object) array_fill_keys(array_keys($expressions), 0);
    }

    /**
     * The request without the filters the tiles set themselves, so the tiles keep showing every
     * status side by side while still following project, period and search.
     */
    public static function without(Request $request, array $keys = ['status']): Request
    {
        $copy = $request->duplicate(array_diff_key($request->query(), array_flip($keys)));
        $copy->setUserResolver($request->getUserResolver());

        return $copy;
    }

    /** "1 sale", "3 sales". */
    public static function many(mixed $n, string $word): string
    {
        return number_format((int) $n).' '.Str::plural($word, (int) $n);
    }

    /** "?, ?, ?" for an IN list of the given values. */
    public static function marks(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    public function money(string $label, mixed $value, string $icon = 'tag', string $tone = 'info', ?array $preset = null, ?string $note = null): self
    {
        return $this->add($label, Format::money(Money::amount((string) ($value ?? 0)), false), $icon, $tone, $preset, $note);
    }

    public function count(string $label, mixed $value, string $icon = 'list', string $tone = 'info', ?array $preset = null, ?string $note = null): self
    {
        return $this->add($label, number_format((float) ($value ?? 0)), $icon, $tone, $preset, $note);
    }

    public function text(string $label, string $value, string $icon = 'info', string $tone = 'info', ?array $preset = null, ?string $note = null): self
    {
        return $this->add($label, $value, $icon, $tone, $preset, $note);
    }

    public function when(bool $condition, callable $callback): self
    {
        if ($condition) {
            $callback($this);
        }

        return $this;
    }

    private function add(string $label, string $value, string $icon, string $tone, ?array $preset, ?string $note): self
    {
        $active = $preset && $this->request && collect($preset)->every(fn ($v, $k) => (string) $this->request->input($k) === (string) $v);
        $this->tiles[] = compact('label', 'value', 'icon', 'tone', 'preset', 'note', 'active');

        return $this;
    }

    public function tiles(): array
    {
        return $this->tiles;
    }

    public function render(): string
    {
        return view('components.list-summary', ['tiles' => $this->tiles])->render();
    }
}
