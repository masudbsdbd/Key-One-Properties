<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->char('idempotency_key', 64)->unique();
            $table->string('source_module', 40);
            $table->string('source_type', 80);
            $table->unsignedBigInteger('source_id');
            $table->string('event_type', 80);
            $table->foreignId('project_id')->nullable()->constrained();
            $table->foreignId('flat_id')->nullable()->constrained();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->decimal('amount', 18, 2)->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->date('effective_at');
            $table->timestamp('occurred_at');
            $table->json('payload')->nullable();
            $table->string('processing_status', 20)->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['processing_status', 'id']);
            $table->index(['source_type', 'source_id']);
            $table->index(['project_id', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_events');
    }
};
