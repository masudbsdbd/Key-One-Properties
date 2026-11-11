<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['supplier_payments', 'contractor_payments', 'expenses', 'customer_payments', 'customer_refunds', 'investor_capital_transactions', 'investor_payouts'];

    private const WITH_TAX = ['supplier_payments', 'contractor_payments'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->foreignId('money_account_id')->nullable()->constrained();
                if (in_array($name, self::WITH_TAX, true)) {
                    // Part of the amount kept back as tax at source; the rest left the money account.
                    $table->decimal('tax_deducted', 18, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropConstrainedForeignId('money_account_id');
                if (in_array($name, self::WITH_TAX, true)) {
                    $table->dropColumn('tax_deducted');
                }
            });
        }
    }
};
