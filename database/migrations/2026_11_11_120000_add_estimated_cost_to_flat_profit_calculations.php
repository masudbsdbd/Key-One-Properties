<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The unit's share of the project budget that is not spent yet, counted as cost until the project is complete.
        Schema::table('flat_profit_calculations', function (Blueprint $table) {
            $table->decimal('estimated_cost_amount', 18, 2)->default(0)->after('other_approved_cost_amount');
        });
    }

    public function down(): void
    {
        Schema::table('flat_profit_calculations', fn (Blueprint $table) => $table->dropColumn('estimated_cost_amount'));
    }
};
