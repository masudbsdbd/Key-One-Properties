<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location_text');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('start_date');
            $table->date('target_completion_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->decimal('estimated_budget', 18, 2)->nullable();
            $table->string('status', 20)->default('planned');
            $table->unsignedBigInteger('cover_media_id')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deleted_at']);
            $table->index('name');
        });

        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('membership_role', 60)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('project_stages', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('sequence');
            $table->string('status', 20)->default('not_started');
            $table->date('planned_start_at')->nullable();
            $table->date('planned_end_at')->nullable();
            $table->date('actual_start_at')->nullable();
            $table->date('actual_end_at')->nullable();
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'sequence']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('project_progress_updates', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('project_stages')->nullOnDelete();
            $table->decimal('progress_percent', 5, 2);
            $table->decimal('previous_percent', 5, 2)->nullable();
            $table->text('summary');
            $table->date('report_date');
            $table->string('visibility', 20)->default('internal');
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->index(['project_id', 'report_date']);
        });

        Schema::create('project_media', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('disk', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('visibility', 20)->default('internal');
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('cover_media_id')->references('id')->on('project_media')->nullOnDelete();
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('batch_uuid');
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'created_at']);
            $table->dropColumn('project_id');
        });
        Schema::table('projects', fn (Blueprint $table) => $table->dropForeign(['cover_media_id']));
        Schema::dropIfExists('project_media');
        Schema::dropIfExists('project_progress_updates');
        Schema::dropIfExists('project_stages');
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('projects');
    }
};
