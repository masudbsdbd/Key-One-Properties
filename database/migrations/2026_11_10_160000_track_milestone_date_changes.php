<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_milestones', function (Blueprint $table) {
            $table->date('original_planned_at')->nullable()->after('planned_at');
        });
        DB::table('construction_milestones')->update(['original_planned_at' => DB::raw('planned_at')]);

        Schema::create('construction_milestone_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained('construction_milestones')->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('reason', 255);
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_milestone_changes');
        Schema::table('construction_milestones', fn (Blueprint $table) => $table->dropColumn('original_planned_at'));
    }
};
