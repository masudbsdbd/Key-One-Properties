<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investor_flat_agreements', function (Blueprint $table) {
            $table->foreignId('supersedes_id')->nullable()->after('version')->constrained('investor_flat_agreements');
            $table->text('terms')->nullable()->after('effective_to');
            $table->foreignId('closed_by')->nullable()->after('approved_at')->constrained('users');
            $table->timestamp('closed_at')->nullable()->after('closed_by');
        });
    }

    public function down(): void
    {
        Schema::table('investor_flat_agreements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supersedes_id');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['terms', 'closed_at']);
        });
    }
};
