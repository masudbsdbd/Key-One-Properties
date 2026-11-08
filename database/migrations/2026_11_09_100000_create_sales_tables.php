<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flat_price_history', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('changed_by')->constrained('users');
        });

        Schema::create('flat_price_revisions', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('flat_id')->constrained();
            $table->decimal('old_price', 18, 2)->nullable();
            $table->decimal('new_price', 18, 2);
            $table->string('reason');
            $table->string('status', 20)->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['flat_id', 'status']);
        });

        Schema::create('flat_bookings', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('booking_no', 30)->unique();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('flat_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->date('booking_date');
            $table->date('booking_expires_at')->nullable();
            $table->decimal('asking_price_snapshot', 18, 2)->nullable();
            $table->decimal('agreed_price', 18, 2);
            $table->decimal('booking_amount_expected', 18, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('status_reason')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['project_id', 'status', 'created_at']);
            $table->index(['flat_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('flat_sales', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('sale_no', 30)->unique();
            $table->foreignId('booking_id')->nullable()->unique()->constrained('flat_bookings');
            $table->foreignId('project_id')->constrained();
            $table->foreignId('flat_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->date('agreement_date');
            $table->decimal('sale_price', 18, 2);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('net_contract_value', 18, 2);
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('status_reason')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['project_id', 'status', 'created_at']);
            $table->index(['flat_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        // One live claim per flat: the unique key decides concurrent bookings and sales.
        Schema::create('active_flat_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->unique()->constrained();
            $table->string('claimant_type', 120);
            $table->unsignedBigInteger('claimant_id');
            $table->foreignId('claimed_by')->constrained('users');
            $table->timestamp('claimed_at');
            $table->timestamps();

            $table->index(['claimant_type', 'claimant_id']);
        });

        Schema::create('sale_agreements', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('sale_id')->unique()->constrained('flat_sales');
            $table->string('agreement_no', 30)->unique();
            $table->date('signed_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('sales_cancellations', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('cancellation_no', 30)->unique();
            $table->foreignId('booking_id')->nullable()->constrained('flat_bookings');
            $table->foreignId('sale_id')->nullable()->constrained('flat_sales');
            $table->string('reason');
            $table->decimal('refund_expected', 18, 2)->default(0);
            $table->string('status', 20)->default('requested');
            $table->string('rejection_reason')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('requested_at');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('customer_refunds', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('refund_no', 30)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('booking_id')->nullable()->constrained('flat_bookings');
            $table->foreignId('sale_id')->nullable()->constrained('flat_sales');
            $table->foreignId('cancellation_id')->nullable()->constrained('sales_cancellations');
            $table->decimal('amount', 18, 2);
            $table->string('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users');
            $table->date('paid_on')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference', 80)->nullable();
            $table->string('status_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->char('idempotency_key', 36)->unique();
            $table->foreignId('reversal_of_id')->nullable()->constrained('customer_refunds');
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['project_id', 'status', 'created_at']);
        });

        Schema::create('flat_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('flat_sales');
            $table->string('status', 20)->default('pending');
            $table->string('registration_no', 60)->nullable();
            $table->date('registered_at')->nullable();
            $table->decimal('registration_cost', 18, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('flat_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('flat_sales');
            $table->string('status', 20)->default('pending');
            $table->date('scheduled_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->json('checklist_json')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['flat_handovers', 'flat_registrations', 'customer_refunds', 'sales_cancellations', 'sale_agreements',
            'active_flat_claims', 'flat_sales', 'flat_bookings', 'flat_price_revisions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('flat_price_history', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
        });
    }
};
