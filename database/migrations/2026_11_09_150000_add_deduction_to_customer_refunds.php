<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_refunds', function (Blueprint $table) {
            $table->decimal('deduction_percent', 7, 4)->default(0)->after('amount');
            $table->decimal('deduction_amount', 18, 2)->default(0)->after('deduction_percent');
            $table->decimal('net_amount', 18, 2)->nullable()->after('deduction_amount');
        });
        DB::table('customer_refunds')->whereIn('status', ['approved', 'paid', 'reversed'])->update(['net_amount' => DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::table('customer_refunds', function (Blueprint $table) {
            $table->dropColumn(['deduction_percent', 'deduction_amount', 'net_amount']);
        });
    }
};
