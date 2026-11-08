<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('material_categories')->nullOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->timestamps();
        });

        Schema::create('material_units', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('code', 20)->unique();
            $table->string('name', 60);
            $table->unsignedTinyInteger('precision')->default(3);
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('sku', 40)->unique();
            $table->string('name', 160);
            $table->foreignId('category_id')->constrained('material_categories');
            $table->foreignId('unit_id')->constrained('material_units');
            $table->decimal('reorder_level', 18, 3)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->foreignId('project_id')->nullable()->constrained();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('material_id')->constrained();
            $table->decimal('owned_qty', 18, 3)->default(0);
            $table->decimal('borrowed_in_qty', 18, 3)->default(0);
            $table->decimal('borrowed_out_qty', 18, 3)->default(0);
            $table->decimal('owned_value', 18, 2)->default(0);
            $table->decimal('avg_unit_cost', 18, 4)->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'material_id']);
            $table->index('material_id');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('movement_no', 30)->unique();
            $table->foreignId('material_id')->constrained();
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses');
            $table->string('movement_type', 30);
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('total_value', 18, 2);
            $table->foreignId('ownership_from_project_id')->nullable()->constrained('projects');
            $table->foreignId('ownership_to_project_id')->nullable()->constrained('projects');
            $table->string('reference_type', 120);
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('reversal_of_id')->nullable()->constrained('stock_movements');
            $table->string('notes')->nullable();
            $table->timestamp('posted_at');
            $table->foreignId('posted_by')->constrained('users');
            $table->char('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->index(['material_id', 'posted_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['from_warehouse_id', 'posted_at']);
            $table->index(['to_warehouse_id', 'posted_at']);
        });

        // Manual receipts (opening stock and other non-purchase receipts).
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('receipt_no', 30)->unique();
            $table->foreignId('warehouse_id')->constrained();
            $table->date('received_at');
            $table->string('source', 20);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('created_by')->constrained('users');
            $table->char('idempotency_key', 36)->unique();
            $table->timestamps();
        });

        Schema::create('stock_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('total_value', 18, 2);
            $table->timestamps();
        });

        Schema::create('stock_issues', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('issue_no', 30)->unique();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('work_package_id')->nullable()->constrained('construction_work_packages');
            $table->date('issued_at');
            $table->string('purpose')->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->char('idempotency_key', 36)->unique();
            $table->timestamps();

            $table->index(['project_id', 'issued_at']);
        });

        Schema::create('stock_issue_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('total_value', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('adjustment_no', 30)->unique();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('reason');
            $table->string('status', 20)->default('submitted');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->char('idempotency_key', 36)->unique();
            $table->timestamps();
        });

        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained();
            $table->decimal('qty_delta', 18, 3);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('valuation_delta', 18, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('material_borrows', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('borrow_no', 30)->unique();
            $table->foreignId('source_project_id')->constrained('projects');
            $table->foreignId('destination_project_id')->constrained('projects');
            $table->foreignId('source_warehouse_id')->constrained('warehouses');
            $table->foreignId('destination_warehouse_id')->constrained('warehouses');
            $table->string('status', 30)->default('submitted');
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users');
            $table->timestamp('issued_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('material_borrow_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_id')->constrained('material_borrows')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained();
            $table->decimal('requested_qty', 18, 3);
            $table->decimal('approved_qty', 18, 3)->nullable();
            $table->decimal('issued_qty', 18, 3)->default(0);
            $table->decimal('returned_qty', 18, 3)->default(0);
            $table->decimal('converted_qty', 18, 3)->default(0);
            $table->decimal('locked_unit_cost', 18, 4)->nullable();
            $table->timestamps();

            $table->unique(['borrow_id', 'material_id']);
        });

        Schema::create('material_borrow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_id')->constrained('material_borrows')->cascadeOnDelete();
            $table->string('action', 30);
            $table->foreignId('actor_id')->constrained('users');
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->char('idempotency_key', 36)->nullable()->unique();
            $table->timestamp('created_at');
        });

        Schema::create('material_transfers', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('transfer_no', 30)->unique();
            $table->string('kind', 20)->default('direct');
            $table->foreignId('source_project_id')->nullable()->constrained('projects');
            $table->foreignId('destination_project_id')->nullable()->constrained('projects');
            $table->foreignId('source_warehouse_id')->constrained('warehouses');
            $table->foreignId('destination_warehouse_id')->constrained('warehouses');
            $table->foreignId('borrow_id')->nullable()->constrained('material_borrows');
            $table->string('status', 20)->default('submitted');
            $table->string('reason')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->char('idempotency_key', 36)->nullable()->unique();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('material_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('material_transfers')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('total_value', 18, 2)->nullable();
            $table->foreignId('borrow_line_id')->nullable()->constrained('material_borrow_lines');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['material_transfer_lines', 'material_transfers', 'material_borrow_actions', 'material_borrow_lines', 'material_borrows',
            'stock_adjustment_lines', 'stock_adjustments', 'stock_issue_lines', 'stock_issues', 'stock_receipt_lines', 'stock_receipts',
            'stock_movements', 'stock_balances', 'warehouses', 'materials', 'material_units', 'material_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
