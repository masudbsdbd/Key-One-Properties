<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Flat-direct costs carry the flat; project-level rows (null) form the shared pool.
        Schema::table('project_cost_allocations', function (Blueprint $table) {
            $table->foreignId('flat_id')->nullable()->after('project_id')->constrained();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('flat_id')->nullable()->after('work_package_id')->constrained();
        });

        Schema::create('investors', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('investor_code', 20)->unique();
            $table->string('name', 160);
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('tax_id', 40)->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('portal_user_id')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('investor_flat_agreements', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('agreement_no', 30)->unique();
            $table->foreignId('investor_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('flat_id')->constrained();
            $table->string('investment_scope', 10)->default('flat');
            $table->decimal('committed_capital', 18, 2);
            $table->decimal('profit_share_percent', 7, 4);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('status_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['flat_id', 'status', 'effective_from']);
            $table->index(['investor_id', 'flat_id']);
        });

        Schema::create('investor_capital_transactions', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('transaction_no', 30)->unique();
            $table->foreignId('investor_id')->constrained();
            $table->foreignId('agreement_id')->constrained('investor_flat_agreements');
            $table->foreignId('flat_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->string('transaction_type', 20);
            $table->decimal('amount', 18, 2);
            $table->date('transaction_date');
            $table->string('payment_method', 20)->nullable();
            $table->string('reference_no', 80)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('status_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->char('idempotency_key', 36)->unique();
            $table->timestamps();

            $table->index(['investor_id', 'status']);
        });

        Schema::create('flat_cost_allocation_runs', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('run_no', 30)->unique();
            $table->foreignId('project_id')->constrained();
            $table->date('cost_cutoff');
            $table->decimal('total_saleable_area', 14, 3);
            $table->decimal('eligible_shared_cost', 18, 2);
            $table->unsignedInteger('calculation_version')->default(1);
            $table->string('status', 20)->default('draft');
            $table->json('snapshot_json');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('flat_cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_run_id')->constrained('flat_cost_allocation_runs');
            $table->foreignId('project_id')->constrained();
            $table->foreignId('flat_id')->constrained();
            $table->string('cost_category', 20);
            $table->string('allocation_type', 10);
            $table->string('basis', 20);
            $table->decimal('area_snapshot', 14, 3)->nullable();
            $table->decimal('allocated_amount', 18, 2);
            $table->timestamps();

            $table->unique(['cost_run_id', 'flat_id', 'cost_category', 'allocation_type'], 'fca_run_flat_unique');
        });

        Schema::create('flat_profit_calculations', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('calculation_no', 30)->unique();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('flat_id')->constrained();
            $table->foreignId('sale_id')->nullable()->constrained('flat_sales');
            $table->foreignId('cost_run_id')->nullable()->constrained('flat_cost_allocation_runs');
            $table->decimal('revenue_amount', 18, 2);
            $table->decimal('direct_cost_amount', 18, 2);
            $table->decimal('shared_cost_amount', 18, 2);
            $table->decimal('other_approved_cost_amount', 18, 2)->default(0);
            $table->decimal('net_profit_amount', 18, 2);
            $table->decimal('investor_share_percent', 7, 4)->default(0);
            $table->decimal('company_share_amount', 18, 2)->default(0);
            $table->string('recognition_basis', 30);
            $table->boolean('revenue_eligible')->default(false);
            $table->json('basis_snapshot');
            $table->string('status', 20)->default('draft');
            $table->string('status_reason')->nullable();
            $table->foreignId('calculated_by')->constrained('users');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['flat_id', 'status']);
        });

        Schema::create('investor_profit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profit_calculation_id')->constrained('flat_profit_calculations');
            $table->foreignId('investor_agreement_id')->constrained('investor_flat_agreements');
            $table->foreignId('investor_id')->constrained();
            $table->foreignId('flat_id')->constrained();
            $table->decimal('share_percent_snapshot', 7, 4);
            $table->decimal('profit_amount', 18, 2);
            $table->decimal('previously_approved', 18, 2)->default(0);
            $table->decimal('payable_change', 18, 2)->default(0);
            $table->string('status', 20)->default('proposed');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['profit_calculation_id', 'investor_agreement_id'], 'ipa_calc_agreement_unique');
            $table->index(['investor_id', 'flat_id']);
        });

        Schema::create('investor_profit_distributions', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('distribution_no', 30)->unique();
            $table->foreignId('investor_id')->constrained();
            $table->foreignId('flat_id')->constrained();
            $table->decimal('amount', 18, 2);
            $table->string('status', 20)->default('pending_approval');
            $table->string('status_reason')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->char('idempotency_key', 36)->unique();
            $table->timestamps();

            $table->index(['investor_id', 'status']);
        });

        Schema::create('investor_payouts', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('payout_no', 30)->unique();
            $table->foreignId('distribution_id')->constrained('investor_profit_distributions');
            $table->foreignId('investor_id')->constrained();
            $table->decimal('amount', 18, 2);
            $table->date('paid_at');
            $table->string('payment_method', 20);
            $table->string('payment_reference', 80)->nullable();
            $table->string('status', 20)->default('posted');
            $table->string('status_reason')->nullable();
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->char('idempotency_key', 36)->unique();
            $table->timestamps();
        });

        Schema::create('investor_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_id')->constrained();
            $table->foreignId('agreement_id')->nullable()->constrained('investor_flat_agreements');
            $table->foreignId('flat_id')->nullable()->constrained();
            $table->string('source_type', 120);
            $table->unsignedBigInteger('source_id');
            $table->date('entry_date');
            $table->string('entry_kind', 20);
            $table->decimal('amount', 18, 2);
            $table->string('description');
            $table->timestamp('posted_at');
            $table->foreignId('reversal_of_id')->nullable()->constrained('investor_ledger_entries');
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'entry_kind'], 'ile_source_unique');
            $table->index(['investor_id', 'entry_date', 'id']);
            $table->index(['investor_id', 'flat_id']);
        });
    }

    public function down(): void
    {
        foreach (['investor_ledger_entries', 'investor_payouts', 'investor_profit_distributions', 'investor_profit_allocations', 'flat_profit_calculations',
            'flat_cost_allocations', 'flat_cost_allocation_runs', 'investor_capital_transactions', 'investor_flat_agreements', 'investors'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('flat_id'));
        Schema::table('project_cost_allocations', fn (Blueprint $table) => $table->dropConstrainedForeignId('flat_id'));
    }
};
