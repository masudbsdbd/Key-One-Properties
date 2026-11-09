<?php

use App\Models\Project;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Project::query()->whereDoesntHave('warehouses')->orderBy('id')->each(fn (Project $project) => Warehouse::openSiteStore($project));
    }

    // Stores may hold stock by the time of a rollback, so they are kept.
    public function down(): void {}
};
