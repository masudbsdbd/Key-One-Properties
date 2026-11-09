<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Client decision: unit-tagged project expenses are direct; other project cost is shared by saleable area.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('settings')->where('key', 'investors.cost_policy')->whereNotNull('value')->exists()) {
            DB::table('settings')->updateOrInsert(['key' => 'investors.cost_policy'], ['value' => 'area_shared', 'created_at' => now(), 'updated_at' => now()]);
            Cache::forget('app.settings');
        }
    }

    public function down(): void
    {
        // Settings stay as the client left them.
    }
};
