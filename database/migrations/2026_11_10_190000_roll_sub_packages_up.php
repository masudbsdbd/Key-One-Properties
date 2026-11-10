<?php

use App\Models\WorkPackage;
use App\Modules\Construction\Services\StageProgress;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        WorkPackage::query()->whereNotNull('parent_id')->distinct()->pluck('parent_id')->each(fn ($id) => StageProgress::syncPackage((int) $id));
        WorkPackage::query()->whereNotNull('stage_id')->distinct()->pluck('stage_id')->each(fn ($id) => StageProgress::sync((int) $id));
    }

    // Progress is recomputed from packages on every change; nothing to undo.
    public function down(): void {}
};
