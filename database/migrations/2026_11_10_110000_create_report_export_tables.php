<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('report_key', 60);
            $table->foreignId('requested_by')->constrained('users');
            $table->char('filter_hash', 64);
            $table->json('filters_json');
            $table->string('format', 10);
            $table->string('status', 20)->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->string('error_summary')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'status', 'created_at']);
        });

        Schema::create('report_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_run_id')->constrained('report_runs');
            $table->string('storage_disk', 30);
            $table->string('storage_path');
            $table->string('file_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_files');
        Schema::dropIfExists('report_runs');
    }
};
