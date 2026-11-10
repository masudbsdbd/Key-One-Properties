<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_issue_lines', function (Blueprint $table) {
            $table->decimal('returned_qty', 18, 3)->default(0)->after('total_value');
        });

        Schema::create('stock_issue_returns', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('return_no', 30)->unique();
            $table->foreignId('stock_issue_id')->constrained();
            $table->date('returned_at');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->char('idempotency_key', 36)->unique();
            $table->timestamps();
        });

        Schema::create('stock_issue_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_issue_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_issue_line_id')->constrained();
            $table->foreignId('material_id')->constrained();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('total_value', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_issue_return_lines');
        Schema::dropIfExists('stock_issue_returns');
        Schema::table('stock_issue_lines', fn (Blueprint $table) => $table->dropColumn('returned_qty'));
    }
};
