@php($progress = $project->overallProgress())
@php($completed = $project->stages->where('status', 'completed')->count())
<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
    <div class="card flex items-center gap-4 p-5">
        <span class="icon-tile tone-accent"><x-icon name="bar-chart" size="h-7 w-7" /></span>
        <div class="min-w-0 flex-1">
            <p class="text-[15px] text-ink-700">Overall Progress</p>
            <p class="text-[28px] font-semibold leading-8 tabular-nums text-ink-900">{{ App\Support\Format::percent($progress) }}</p>
            <x-progress :value="$progress" :label="false" class="mt-2" />
        </div>
    </div>
    <x-stat-card label="Status" :value="App\Models\Project::STATUSES[$project->status] ?? $project->status" icon="activity" />
    <x-stat-card label="Stages Completed" :value="$completed.' / '.$project->stages->count()" icon="check-square" />
    <x-stat-card label="Team Members" :value="$project->members_count" icon="users" />
</div>
