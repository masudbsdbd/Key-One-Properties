<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A unit number must be unique among live units of a building only; deleted units keep their history
 * but no longer block the number. live_flag is 1 for live rows and NULL for deleted ones, and NULLs
 * never collide in a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flats', function (Blueprint $table) {
            $table->unsignedTinyInteger('live_flag')->nullable()->storedAs('IF(deleted_at IS NULL, 1, NULL)')->after('deleted_at');
        });
        Schema::table('flats', function (Blueprint $table) {
            $table->unique(['building_id', 'flat_no', 'live_flag'], 'flats_building_flat_live_unique');
            $table->dropUnique(['building_id', 'flat_no']);
        });
    }

    public function down(): void
    {
        Schema::table('flats', function (Blueprint $table) {
            $table->unique(['building_id', 'flat_no']);
            $table->dropUnique('flats_building_flat_live_unique');
        });
        Schema::table('flats', function (Blueprint $table) {
            $table->dropColumn('live_flag');
        });
    }
};
