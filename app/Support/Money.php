<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Exact decimal arithmetic for money (2 dp), quantities (3 dp) and unit costs (4 dp).
 * Values travel as strings; floats are never used for stored amounts.
 */
class Money
{
    public static function of(string|int|float|BigDecimal|null $value): BigDecimal
    {
        if ($value instanceof BigDecimal) {
            return $value;
        }
        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }
        if (is_float($value)) {
            $value = number_format($value, 6, '.', '');
        }

        return BigDecimal::of(str_replace(',', '', (string) $value));
    }

    public static function amount(string|int|float|BigDecimal|null $value): string
    {
        return (string) self::of($value)->toScale(2, RoundingMode::HalfUp);
    }

    public static function qty(string|int|float|BigDecimal|null $value): string
    {
        return (string) self::of($value)->toScale(3, RoundingMode::HalfUp);
    }

    public static function rate(string|int|float|BigDecimal|null $value): string
    {
        return (string) self::of($value)->toScale(4, RoundingMode::HalfUp);
    }

    public static function mul(string|BigDecimal $a, string|BigDecimal $b, int $scale = 2): string
    {
        return (string) self::of($a)->multipliedBy(self::of($b))->toScale($scale, RoundingMode::HalfUp);
    }

    public static function div(string|BigDecimal $a, string|BigDecimal $b, int $scale = 4): string
    {
        $divisor = self::of($b);

        return $divisor->isZero() ? (string) BigDecimal::zero()->toScale($scale) : (string) self::of($a)->dividedBy($divisor, $scale, RoundingMode::HalfUp);
    }

    public static function add(string|BigDecimal ...$values): string
    {
        return (string) array_reduce($values, fn (BigDecimal $carry, $v) => $carry->plus(self::of($v)), BigDecimal::zero());
    }

    public static function sub(string|BigDecimal $a, string|BigDecimal $b): string
    {
        return (string) self::of($a)->minus(self::of($b));
    }

    public static function cmp(string|BigDecimal|int|null $a, string|BigDecimal|int|null $b): int
    {
        return self::of($a)->compareTo(self::of($b));
    }

    public static function isPositive(string|BigDecimal|null $value): bool
    {
        return self::of($value)->isPositive();
    }

    public static function neg(string|BigDecimal $value): string
    {
        return (string) self::of($value)->negated();
    }
}
