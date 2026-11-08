<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('contractor_code', 20)->unique();
            $table->string('name', 160);
            $table->string('trade', 80)->nullable();
            $table->string('contact_person', 120)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('tax_id', 40)->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contractor_work_orders', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('wo_no', 30)->unique();
            $table->foreignId('contractor_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('work_package_id')->nullable()->constrained('construction_work_packages');
            $table->string('title', 160);
            $table->text('scope')->nullable();
            $table->decimal('contract_amount', 18, 2);
            $table->decimal('retention_percent', 5, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('status_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['contractor_id', 'status']);
        });

        Schema::create('contractor_measurements', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('work_order_id')->constrained('contractor_work_orders');
            $table->date('measurement_date');
            $table->string('description');
            $table->decimal('quantity', 18, 3);
            $table->string('unit', 20);
            $table->decimal('rate', 18, 4);
            $table->decimal('measured_amount', 18, 2);
            $table->string('status', 20)->default('draft');
            $table->string('rejection_reason')->nullable();
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'status']);
        });

        Schema::create('contractor_bills', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('bill_no', 30)->unique();
            $table->string('kind', 20)->default('running');
            $table->foreignId('work_order_id')->constrained('contractor_work_orders');
            $table->foreignId('contractor_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->date('bill_date');
            $table->decimal('gross_amount', 18, 2);
            $table->decimal('advance_adjustment', 18, 2)->default(0);
            $table->decimal('retention_amount', 18, 2)->default(0);
            $table->decimal('other_deductions', 18, 2)->default(0);
            $table->string('deduction_note')->nullable();
            $table->decimal('net_payable', 18, 2);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['contractor_id', 'status', 'bill_date']);
            $table->index(['project_id', 'status', 'bill_date']);
        });

        Schema::create('contractor_bill_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_bill_id')->constrained('contractor_bills');
            $table->foreignId('measurement_id')->unique()->constrained('contractor_measurements');
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });

        Schema::create('contractor_payments', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('payment_no', 30)->unique();
            $table->foreignId('contractor_id')->constrained();
            $table->foreignId('work_order_id')->constrained('contractor_work_orders');
            $table->foreignId('bill_id')->nullable()->constrained('contractor_bills');
            $table->foreignId('project_id')->constrained();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('payment_method', 20);
            $table->string('reference', 80)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->char('idempotency_key', 36)->unique();
            $table->timestamps();

            $table->index(['contractor_id', 'status', 'payment_date']);
            $table->index(['work_order_id', 'status']);
        });

        Schema::create('contractor_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained();
            $table->string('entry_type', 20);
            $table->string('source_type', 120);
            $table->unsignedBigInteger('source_id');
            $table->date('entry_date');
            $table->string('description');
            $table->decimal('charge_amount', 18, 2)->default(0);
            $table->decimal('payment_amount', 18, 2)->default(0);
            $table->foreignId('reversal_of_id')->nullable()->constrained('contractor_ledger_entries');
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'entry_type'], 'cle_source_unique');
            $table->index(['contractor_id', 'entry_date', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['contractor_ledger_entries', 'contractor_payments', 'contractor_bill_measurements', 'contractor_bills',
            'contractor_measurements', 'contractor_work_orders', 'contractors'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
