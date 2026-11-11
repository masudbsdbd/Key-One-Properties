<x-layouts.portal :title="$project->name" portal="investor">
    <x-page-header :title="$project->name" :breadcrumbs="['Home' => route('investor.dashboard'), 'Projects' => route('investor.projects.index'), $project->code => null]">
        <x-slot:actions>@if ($canFinancials)<x-button variant="secondary" icon="bar-chart" href="{{ route('investor.projects.financials', $project->public_id) }}">Financials</x-button>@endif</x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Construction" :value="App\Support\Format::percent($project->overallProgress())" icon="activity" tone="accent" />
        <x-stat-card label="Milestones Done" :value="$milestones->where('status', 'completed')->count().' / '.$milestones->count()" icon="check-square" tone="success" />
        <x-stat-card label="Your Units" :value="$units->pluck('flat.flat_no')->unique()->join(', ') ?: '—'" icon="door" tone="info" />
    </div>

    @include('portal._project-progress', ['photoRoute' => 'investor.projects.photo'])
</x-layouts.portal>
