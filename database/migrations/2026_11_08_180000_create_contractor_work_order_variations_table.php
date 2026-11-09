<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_work_order_variations', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('variation_no', 30)->unique();
            $table->foreignId('work_order_id')->constrained('contractor_work_orders');
            $table->decimal('old_amount', 18, 2);
            $table->decimal('new_amount', 18, 2);
            $table->string('reason');
            $table->string('status', 20)->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_work_order_variations');
    }
};
