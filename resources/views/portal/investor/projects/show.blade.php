<x-layouts.portal :title="$project->name" portal="investor">
    <x-page-header :title="$project->name" :breadcrumbs="['Home' => route('investor.dashboard'), 'Project Progress' => route('investor.projects.index'), $project->name => null]">
        <x-slot:actions>@if ($canFinancials)<x-button variant="secondary" icon="bar-chart" href="{{ route('investor.projects.financials', $project->public_id) }}">Project Cost</x-button>@endif</x-slot:actions>
    </x-page-header>

    <div @class(['mb-6 grid gap-4 sm:grid-cols-2', 'xl:grid-cols-4' => $project->target_completion_date, 'xl:grid-cols-3' => ! $project->target_completion_date])>
        <x-stat-card label="Construction" :value="App\Support\Format::percent($project->overallProgress())" icon="activity" tone="accent" />
        <x-stat-card label="Milestones Done" :value="$milestones->where('status', 'completed')->count().' / '.$milestones->count()" icon="check-square" tone="success" />
        <x-stat-card label="Your Units" :value="$units->pluck('flat.flat_no')->unique()->join(', ') ?: '—'" icon="door" tone="info" />
        @if ($project->target_completion_date)<x-stat-card :label="$project->actual_completion_date ? 'Completed' : 'Expected Completion'" :value="App\Support\Format::date($project->actual_completion_date ?? $project->target_completion_date)" icon="calendar" tone="violet" />@endif
    </div>

    @include('portal._project-progress', ['photoRoute' => 'investor.projects.photo'])
</x-layouts.portal>
