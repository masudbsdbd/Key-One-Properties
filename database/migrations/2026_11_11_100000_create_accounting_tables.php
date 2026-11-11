<?php

use App\Modules\Accounting\Support\ChartOfAccounts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('ledger_accounts');
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('category', 20);
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->string('system_key', 40)->nullable()->unique();
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['category', 'code']);
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('name', 40);
            $table->date('start_date')->unique();
            $table->date('end_date');
            $table->string('status', 20)->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closing_journal_id')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('fiscal_year_id')->constrained();
            $table->string('name', 40);
            $table->date('start_date')->unique();
            $table->date('end_date');
            $table->string('status', 20)->default('open');
            $table->foreignId('locked_by')->nullable()->constrained('users');
            $table->timestamp('locked_at')->nullable();
            $table->string('status_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date']);
        });

        Schema::create('money_accounts', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('kind', 20);
            $table->string('name', 120);
            $table->string('bank_name', 120)->nullable();
            $table->string('account_number', 60)->nullable();
            $table->string('branch', 120)->nullable();
            $table->foreignId('ledger_account_id')->unique()->constrained('ledger_accounts');
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['is_active', 'kind']);
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('money_account_id')->constrained();
            $table->date('statement_date');
            $table->decimal('statement_balance', 18, 2);
            $table->decimal('book_balance', 18, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['money_account_id', 'statement_date']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('journal_no', 30)->unique();
            $table->string('journal_type', 20);
            $table->string('source_key', 120)->nullable()->unique();
            $table->foreignId('financial_event_id')->nullable()->constrained();
            $table->nullableMorphs('source');
            $table->date('accounting_date');
            $table->foreignId('accounting_period_id')->nullable()->constrained();
            $table->string('narration', 500);
            $table->string('reference', 80)->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->foreignId('project_id')->nullable()->constrained();
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries');
            $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries');
            $table->string('status_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['status', 'accounting_date']);
            $table->index(['journal_type', 'accounting_date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('account_id')->constrained('ledger_accounts');
            $table->string('description')->nullable();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->foreignId('project_id')->nullable()->constrained();
            $table->foreignId('flat_id')->nullable()->constrained();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->foreignId('contractor_id')->nullable()->constrained();
            $table->foreignId('investor_id')->nullable()->constrained();
            $table->foreignId('money_account_id')->nullable()->constrained();
            $table->foreignId('bank_reconciliation_id')->nullable()->constrained();

            $table->unique(['journal_entry_id', 'line_no']);
            $table->index(['account_id', 'journal_entry_id']);
            $table->index(['project_id', 'account_id']);
            $table->index(['flat_id', 'account_id']);
            $table->index(['money_account_id', 'journal_entry_id']);
        });
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_one_side CHECK (debit >= 0 AND credit >= 0 AND NOT (debit > 0 AND credit > 0))');

        Schema::create('money_transactions', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('txn_no', 30)->unique();
            $table->string('type', 20);
            $table->string('purpose', 40)->nullable();
            $table->foreignId('from_money_account_id')->nullable()->constrained('money_accounts');
            $table->foreignId('to_money_account_id')->nullable()->constrained('money_accounts');
            $table->foreignId('counter_account_id')->nullable()->constrained('ledger_accounts');
            $table->foreignId('project_id')->nullable()->constrained();
            $table->decimal('amount', 18, 2);
            $table->decimal('charge_amount', 18, 2)->default(0);
            $table->date('txn_date');
            $table->string('party_name', 160)->nullable();
            $table->string('reference', 80)->nullable();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('posted');
            $table->char('idempotency_key', 36)->nullable()->unique();
            $table->foreignId('journal_entry_id')->nullable()->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['type', 'txn_date']);
            $table->index(['from_money_account_id', 'txn_date']);
            $table->index(['to_money_account_id', 'txn_date']);
        });

        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->morphs('source');
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->string('cheque_no', 40);
            $table->string('bank_name', 120)->nullable();
            $table->date('cheque_date')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('status', 20)->default('in_hand');
            $table->foreignId('money_account_id')->nullable()->constrained();
            $table->date('received_on');
            $table->date('deposited_on')->nullable();
            $table->date('settled_on')->nullable();
            $table->string('status_note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['status', 'cheque_date']);
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->foreignId('ledger_account_id')->nullable()->constrained('ledger_accounts');
        });

        ChartOfAccounts::install();
    }

    public function down(): void
    {
        Schema::table('expense_categories', fn (Blueprint $table) => $table->dropConstrainedForeignId('ledger_account_id'));
        foreach (['cheques', 'money_transactions', 'journal_lines', 'journal_entries', 'bank_reconciliations', 'money_accounts', 'accounting_periods', 'fiscal_years', 'ledger_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
