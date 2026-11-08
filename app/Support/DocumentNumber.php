<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Sequential document numbers per prefix and year, e.g. PO-2026-00001.
 * The counter row is locked, so concurrent requests never get the same number.
 */
class DocumentNumber
{
    public static function next(string $prefix, ?int $year = null): string
    {
        $year ??= (int) now(config('app.display_timezone'))->format('Y');

        $number = DB::transaction(function () use ($prefix, $year) {
            DB::table('document_sequences')->insertOrIgnore([
                'prefix' => $prefix, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $row = DB::table('document_sequences')->where('prefix', $prefix)->where('year', $year)->lockForUpdate()->first();
            $next = $row->last_number + 1;
            DB::table('document_sequences')->where('id', $row->id)->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        });

        return sprintf('%s-%d-%05d', $prefix, $year, $number);
    }
}
