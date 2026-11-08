<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Document line inputs are keyed by line position, so internal line ids never reach the browser.
 */
class LineKeys
{
    public static function ordered(Collection $lines): Collection
    {
        return $lines->sortBy('id')->values();
    }

    /** @return array<int, mixed> line id => value */
    public static function toIds(Collection $lines, array $input): array
    {
        $ordered = self::ordered($lines);
        $out = [];
        foreach ($input as $position => $value) {
            if (is_numeric($position) && ($line = $ordered->get((int) $position))) {
                $out[$line->id] = $value;
            }
        }

        return $out;
    }

    /**
     * Runs $callback with id-keyed input and maps id-keyed validation errors back to positions.
     */
    public static function call(Collection $lines, array $input, string $field, callable $callback): mixed
    {
        try {
            return $callback(self::toIds($lines, $input));
        } catch (ValidationException $e) {
            $positions = self::ordered($lines)->pluck('id')->flip();
            $errors = [];
            foreach ($e->errors() as $key => $messages) {
                if (preg_match('/^'.preg_quote($field, '/').'\.(\d+)(.*)$/', $key, $m) && $positions->has((int) $m[1])) {
                    $key = $field.'.'.$positions[(int) $m[1]].$m[2];
                }
                $errors[$key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }
}
