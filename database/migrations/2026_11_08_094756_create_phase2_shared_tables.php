<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['prefix', 'year']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->morphs('attachable');
            $table->string('original_name');
            $table->string('disk', 30);
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('project_cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->string('source_type', 120);
            $table->unsignedBigInteger('source_id');
            $table->string('cost_category', 20);
            $table->decimal('amount', 18, 2);
            $table->string('allocation_scope', 10)->default('direct');
            $table->unsignedBigInteger('work_package_id')->nullable();
            $table->date('effective_at');
            $table->foreignId('reversal_of_id')->nullable()->constrained('project_cost_allocations');
            $table->char('idempotency_key', 64)->unique();
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->index(['project_id', 'cost_category', 'effective_at'], 'pca_project_category_date_idx');
            $table->index(['source_type', 'source_id']);
        });

        Schema::table('financial_events', function (Blueprint $table) {
            $table->foreignId('reversal_of_id')->nullable()->after('payload')->constrained('financial_events');
        });
    }

    public function down(): void
    {
        Schema::table('financial_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_of_id');
        });
        Schema::dropIfExists('project_cost_allocations');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('document_sequences');
    }
};
