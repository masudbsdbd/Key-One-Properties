<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('supplier_code', 20)->unique();
            $table->string('name', 160);
            $table->string('contact_person', 120)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('tax_id', 40)->nullable();
            $table->string('status', 20)->default('active');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('po_no', 30)->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['status', 'order_date']);
            $table->index(['supplier_id', 'order_date']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2);
            $table->decimal('received_qty', 18, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('grn_no', 30)->unique();
            $table->foreignId('purchase_order_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->date('received_at');
            $table->string('supplier_challan_no', 60)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('received_by')->constrained('users');
            $table->timestamp('posted_at');
            $table->char('idempotency_key', 36)->unique();
            $table->timestamps();

            $table->index(['supplier_id', 'received_at']);
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained();
            $table->foreignId('material_id')->constrained();
            $table->decimal('received_qty', 18, 3);
            $table->decimal('accepted_qty', 18, 3);
            $table->decimal('rejected_qty', 18, 3)->default(0);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('returned_qty', 18, 3)->default(0);
            $table->unsignedBigInteger('billed_bill_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('bill_no', 30)->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained();
            $table->string('supplier_invoice_no', 60)->nullable();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status', 'due_date']);
        });

        Schema::create('supplier_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_line_id')->nullable()->constrained();
            $table->string('description');
            $table->decimal('quantity', 18, 3)->nullable();
            $table->decimal('unit_price', 18, 4)->nullable();
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('payment_no', 30)->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('payment_method', 20);
            $table->string('payment_reference', 80)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->char('idempotency_key', 36)->unique();
            $table->timestamps();

            $table->index(['supplier_id', 'status', 'payment_date']);
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('supplier_payments')->cascadeOnDelete();
            $table->foreignId('supplier_bill_id')->constrained();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->unique(['payment_id', 'supplier_bill_id']);
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('return_no', 30)->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('goods_receipt_id')->constrained();
            $table->string('status', 20)->default('posted');
            $table->string('reason');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->boolean('credited')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('posted_at');
            $table->char('idempotency_key', 36)->unique();
            $table->timestamps();
        });

        Schema::create('purchase_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_line_id')->constrained();
            $table->foreignId('material_id')->constrained();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('amount', 18, 2);
            $table->boolean('was_billed')->default(false);
            $table->timestamps();
        });

        Schema::create('supplier_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->string('entry_type', 20);
            $table->string('source_type', 120);
            $table->unsignedBigInteger('source_id');
            $table->date('entry_date');
            $table->string('description');
            $table->decimal('charge_amount', 18, 2)->default(0);
            $table->decimal('payment_amount', 18, 2)->default(0);
            $table->foreignId('reversal_of_id')->nullable()->constrained('supplier_ledger_entries');
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'entry_type'], 'sle_source_unique');
            $table->index(['supplier_id', 'entry_date', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['supplier_ledger_entries', 'purchase_return_lines', 'purchase_returns', 'supplier_payment_allocations', 'supplier_payments',
            'supplier_bill_lines', 'supplier_bills', 'goods_receipt_lines', 'goods_receipts', 'purchase_order_lines', 'purchase_orders', 'suppliers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
