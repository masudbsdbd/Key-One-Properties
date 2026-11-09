<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users, materials and work packages are deleted for good from the user's point of view (no restore), so a
 * deleted row must not keep its email, mobile, SKU or code reserved. live_flag is 1 for live rows and NULL
 * for deleted ones; NULLs never collide in a unique index, so uniqueness now holds among live rows only.
 */
return new class extends Migration
{
    /** table => [old unique columns per index] */
    private const UNIQUES = [
        'users' => [['email'], ['mobile']],
        'materials' => [['sku']],
        'construction_work_packages' => [['project_id', 'code']],
    ];

    public function up(): void
    {
        foreach (self::UNIQUES as $table => $indexes) {
            Schema::table($table, fn (Blueprint $t) => $t->unsignedTinyInteger('live_flag')->nullable()->storedAs('IF(deleted_at IS NULL, 1, NULL)')->after('deleted_at'));
            Schema::table($table, function (Blueprint $t) use ($table, $indexes) {
                foreach ($indexes as $columns) {
                    $t->unique([...$columns, 'live_flag'], $table.'_'.implode('_', $columns).'_live_unique');
                    $t->dropUnique($columns);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::UNIQUES as $table => $indexes) {
            Schema::table($table, function (Blueprint $t) use ($table, $indexes) {
                foreach ($indexes as $columns) {
                    $t->unique($columns);
                    $t->dropUnique($table.'_'.implode('_', $columns).'_live_unique');
                }
            });
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('live_flag'));
        }
    }
};
