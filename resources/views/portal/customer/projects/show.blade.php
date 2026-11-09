<x-layouts.portal :title="$project->name" portal="customer">
    <x-page-header :title="$project->name" :breadcrumbs="['Overview' => route('customer.dashboard'), 'My Units' => route('customer.flats.index'), $project->name => null]" />

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Construction" :value="App\Support\Format::percent($project->overallProgress())" icon="activity" tone="accent" />
        <x-stat-card label="Milestones Done" :value="$milestones->where('status', 'completed')->count().' / '.$milestones->count()" icon="check-square" tone="success" />
        <x-stat-card label="Your Units" :value="$units->join(', ') ?: '—'" icon="door" tone="info" />
    </div>

    @include('portal._project-progress', ['photoRoute' => 'customer.projects.photo'])
</x-layouts.portal>
