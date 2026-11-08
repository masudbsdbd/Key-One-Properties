<?php

namespace App\Modules\Inventory\Services;

use App\Models\StockBalance;
use App\Support\Money;

/**
 * Pure rules for how each movement changes stock balances.
 * Used by posting and by reconciliation so both always agree.
 *
 * A delta is ['owned' => qty, 'in' => qty, 'out' => qty, 'value' => amount] (signed strings).
 */
class StockMath
{
    public const OUTBOUND = ['issue', 'purchase_return', 'adjustment_out'];

    public const INBOUND = ['receipt', 'purchase_receipt', 'adjustment_in'];

    /**
     * Deltas per warehouse id for a movement whose value is already known.
     *
     * @return array<int, array{owned: string, in: string, out: string, value: string}>
     */
    public static function deltas(string $type, ?int $from, ?int $to, string $qty, string $value): array
    {
        $neg = fn (string $v) => Money::neg($v);
        $d = [];
        $add = function (int $warehouse, string $owned = '0', string $in = '0', string $out = '0', string $val = '0') use (&$d) {
            $c = $d[$warehouse] ?? ['owned' => '0', 'in' => '0', 'out' => '0', 'value' => '0'];
            $d[$warehouse] = [
                'owned' => Money::add($c['owned'], $owned), 'in' => Money::add($c['in'], $in),
                'out' => Money::add($c['out'], $out), 'value' => Money::add($c['value'], $val),
            ];
        };

        match (true) {
            in_array($type, self::INBOUND, true) => $add($to, $qty, '0', '0', $value),
            in_array($type, self::OUTBOUND, true) => $add($from, $neg($qty), '0', '0', $neg($value)),
            $type === 'borrow_issue' => [$add($from, $neg($qty), '0', $qty), $add($to, '0', $qty)],
            // Borrow return: from = borrower (destination), to = owner (source).
            $type === 'borrow_return' => [$add($from, '0', $neg($qty)), $add($to, $qty, '0', $neg($qty))],
            // Conversion: from = owner, to = borrower. Ownership and value move together.
            $type === 'borrow_convert' => [$add($from, '0', '0', $neg($qty), $neg($value)), $add($to, $qty, $neg($qty), '0', $value)],
            $type === 'transfer' => [$add($from, $neg($qty), '0', '0', $neg($value)), $add($to, $qty, '0', '0', $value)],
            default => throw new \InvalidArgumentException("Unknown movement type [{$type}]."),
        };

        return $d;
    }

    /**
     * @param  array<int, array{owned: string, in: string, out: string, value: string}>  $deltas
     * @return array<int, array{owned: string, in: string, out: string, value: string}>
     */
    public static function negate(array $deltas): array
    {
        return array_map(fn (array $d) => array_map(fn (string $v) => Money::neg($v), $d), $deltas);
    }

    /**
     * Value of taking $qty out of what a warehouse owns, at weighted average cost.
     * Taking everything takes the full remaining value so no rounding residue is left.
     */
    public static function takeValue(StockBalance $balance, string $qty): string
    {
        $base = Money::add($balance->owned_qty, $balance->borrowed_out_qty);
        if (Money::cmp($qty, $base) >= 0) {
            return Money::amount($balance->owned_value);
        }

        $value = Money::mul($qty, self::avg($balance->owned_value, $base));

        return Money::cmp($value, $balance->owned_value) > 0 ? Money::amount($balance->owned_value) : $value;
    }

    public static function avg(string $value, string $qty): string
    {
        return Money::cmp($qty, '0') > 0 ? Money::div($value, $qty, 4) : '0.0000';
    }

    /**
     * Apply a delta to a balance in memory and refresh its average cost.
     */
    public static function apply(StockBalance $balance, array $delta): void
    {
        $balance->owned_qty = Money::qty(Money::add($balance->owned_qty, $delta['owned']));
        $balance->borrowed_in_qty = Money::qty(Money::add($balance->borrowed_in_qty, $delta['in']));
        $balance->borrowed_out_qty = Money::qty(Money::add($balance->borrowed_out_qty, $delta['out']));
        $balance->owned_value = Money::amount(Money::add($balance->owned_value, $delta['value']));
        $balance->avg_unit_cost = self::avg($balance->owned_value, Money::add($balance->owned_qty, $balance->borrowed_out_qty));
    }

    public static function isValid(StockBalance $balance): bool
    {
        return Money::cmp($balance->owned_qty, '0') >= 0
            && Money::cmp($balance->borrowed_in_qty, '0') >= 0
            && Money::cmp($balance->borrowed_out_qty, '0') >= 0
            && Money::cmp($balance->owned_value, '0') >= 0;
    }
}
