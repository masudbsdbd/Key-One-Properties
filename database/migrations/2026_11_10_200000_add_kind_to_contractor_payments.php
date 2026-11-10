<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractor_payments', function (Blueprint $table) {
            // payment: money paid to the contractor (against a bill, or an advance without one); refund: unused advance paid back.
            $table->string('kind', 20)->default('payment')->after('bill_id');
        });
    }

    public function down(): void
    {
        Schema::table('contractor_payments', fn (Blueprint $table) => $table->dropColumn('kind'));
    }
};
