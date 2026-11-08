<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_work_packages', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('project_id')->constrained();
            $table->string('code', 30);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->foreignId('stage_id')->nullable()->constrained('project_stages')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('construction_work_packages')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('budget_amount', 18, 2)->default(0);
            $table->date('planned_start_at')->nullable();
            $table->date('planned_end_at')->nullable();
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->string('status', 20)->default('not_started');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'code']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('construction_milestones', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('work_package_id')->nullable()->constrained('construction_work_packages')->nullOnDelete();
            $table->string('title', 160);
            $table->date('planned_at');
            $table->date('completed_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'planned_at']);
        });

        Schema::create('construction_progress_updates', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('work_package_id')->nullable()->constrained('construction_work_packages');
            $table->foreignId('milestone_id')->nullable()->constrained('construction_milestones')->nullOnDelete();
            $table->decimal('progress_percent', 5, 2);
            $table->decimal('previous_percent', 5, 2)->nullable();
            $table->date('report_date');
            $table->text('notes');
            $table->string('visibility', 20)->default('internal');
            $table->string('status', 20)->default('submitted');
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status', 'report_date'], 'cpu_project_status_date_idx');
        });

        Schema::create('labour_entries', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('entry_no', 30)->unique();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('work_package_id')->nullable()->constrained('construction_work_packages');
            $table->date('work_date');
            $table->string('labour_type', 40);
            $table->unsignedInteger('headcount');
            $table->decimal('units', 18, 3)->default(1);
            $table->decimal('rate', 18, 4);
            $table->decimal('total_amount', 18, 2);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['project_id', 'status', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labour_entries');
        Schema::dropIfExists('construction_progress_updates');
        Schema::dropIfExists('construction_milestones');
        Schema::dropIfExists('construction_work_packages');
    }
};
