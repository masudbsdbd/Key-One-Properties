<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories');
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('allowed_scope', 10)->default('both');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('expense_no', 30)->unique();
            $table->string('scope', 10);
            $table->foreignId('project_id')->nullable()->constrained();
            $table->foreignId('work_package_id')->nullable()->constrained('construction_work_packages');
            $table->foreignId('category_id')->constrained('expense_categories');
            $table->date('expense_date');
            $table->string('description');
            $table->string('payee_name', 160)->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference', 80)->nullable();
            $table->date('paid_on')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->string('status_reason')->nullable();
            $table->timestamps();

            $table->index(['scope', 'status', 'expense_date']);
            $table->index(['project_id', 'status', 'expense_date']);
            $table->index(['category_id', 'expense_date']);
        });

        Schema::create('expense_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action', 20);
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['expense_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_actions');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
