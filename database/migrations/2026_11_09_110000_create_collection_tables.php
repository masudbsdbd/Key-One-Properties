<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('plan_no', 30)->unique();
            $table->foreignId('sale_id')->constrained('flat_sales');
            $table->foreignId('customer_id')->constrained();
            $table->decimal('contract_value', 18, 2);
            $table->decimal('booking_credit', 18, 2)->default(0);
            $table->decimal('down_payment_amount', 18, 2)->default(0);
            $table->decimal('principal_amount', 18, 2);
            $table->string('installment_frequency', 20)->default('monthly');
            $table->date('start_date');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['sale_id', 'status']);
        });

        Schema::create('installment_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('installment_plans');
            $table->unsignedInteger('sequence_no');
            $table->string('kind', 20)->default('installment');
            $table->date('due_date');
            $table->decimal('principal_due', 18, 2);
            $table->decimal('fee_due', 18, 2)->default(0);
            $table->decimal('principal_paid', 18, 2)->default(0);
            $table->decimal('fee_paid', 18, 2)->default(0);
            $table->string('status', 20)->default('upcoming');
            $table->timestamps();

            $table->unique(['plan_id', 'sequence_no']);
            $table->index(['plan_id', 'status', 'due_date']);
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('payment_no', 30)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->string('purpose', 20);
            $table->foreignId('booking_id')->nullable()->constrained('flat_bookings');
            $table->foreignId('sale_id')->nullable()->constrained('flat_sales');
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('method', 20);
            $table->string('reference_no', 80)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('received_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->string('status_reason')->nullable();
            $table->char('idempotency_key', 36)->unique();
            $table->timestamps();

            $table->index(['customer_id', 'posted_at']);
            $table->index(['sale_id', 'status']);
            $table->index(['booking_id', 'status']);
            $table->index(['project_id', 'status', 'payment_date']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('customer_payments');
            $table->foreignId('schedule_item_id')->nullable()->constrained('installment_schedule_items');
            $table->string('allocation_type', 20);
            $table->decimal('amount', 18, 2);
            $table->foreignId('reversal_of_id')->nullable()->constrained('payment_allocations');
            $table->timestamp('created_at');

            $table->index(['schedule_item_id']);
            $table->index(['payment_id', 'allocation_type']);
        });

        Schema::create('customer_late_fee_policies', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('sale_id')->nullable()->constrained('flat_sales');
            $table->string('fee_type', 10)->default('none');
            $table->decimal('fee_value', 18, 6)->default(0);
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->string('frequency', 10)->default('once');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['customer_id', 'effective_from']);
        });

        Schema::create('late_fee_assessments', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('schedule_item_id')->constrained('installment_schedule_items');
            $table->foreignId('policy_id')->constrained('customer_late_fee_policies');
            $table->date('assessed_on');
            $table->string('period', 10);
            $table->decimal('amount', 18, 2);
            $table->string('status', 20)->default('posted');
            $table->foreignId('assessed_by')->nullable()->constrained('users');
            $table->foreignId('waived_by')->nullable()->constrained('users');
            $table->timestamp('waived_at')->nullable();
            $table->string('reason')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();
        });

        Schema::create('installment_reschedules', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('reschedule_no', 30)->unique();
            $table->foreignId('plan_id')->constrained('installment_plans');
            $table->string('reason');
            $table->json('old_schedule_snapshot');
            $table->json('new_schedule_snapshot');
            $table->string('status', 20)->default('requested');
            $table->string('rejection_reason')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_statement_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('sale_id')->nullable()->constrained('flat_sales');
            $table->foreignId('booking_id')->nullable()->constrained('flat_bookings');
            $table->string('entry_type', 30);
            $table->date('entry_date');
            $table->string('source_type', 120);
            $table->unsignedBigInteger('source_id');
            $table->decimal('charge_amount', 18, 2)->default(0);
            $table->decimal('credit_amount', 18, 2)->default(0);
            $table->string('description');
            $table->timestamp('posted_at');
            $table->foreignId('reversal_of_id')->nullable()->constrained('customer_statement_entries');
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'entry_type'], 'cse_source_unique');
            $table->index(['customer_id', 'entry_date', 'id']);
            $table->index(['sale_id', 'entry_date']);
        });

        Schema::create('money_receipts', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('receipt_no', 30)->unique();
            $table->foreignId('payment_id')->unique()->constrained('customer_payments');
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->constrained('users');
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['money_receipts', 'customer_statement_entries', 'installment_reschedules', 'late_fee_assessments', 'customer_late_fee_policies',
            'payment_allocations', 'customer_payments', 'installment_schedule_items', 'installment_plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
