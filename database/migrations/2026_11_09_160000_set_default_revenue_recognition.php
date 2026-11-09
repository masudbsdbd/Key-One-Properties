<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Client decision: profit is recognised after sale approval, when an admin approves the calculation.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('settings')->where('key', 'investors.revenue_recognition')->whereNotNull('value')->exists();
        if (! $exists) {
            DB::table('settings')->updateOrInsert(['key' => 'investors.revenue_recognition'], ['value' => 'sale_approved', 'created_at' => now(), 'updated_at' => now()]);
            Cache::forget('app.settings');
        }
    }

    public function down(): void
    {
        // Settings stay as the client left them.
    }
};
