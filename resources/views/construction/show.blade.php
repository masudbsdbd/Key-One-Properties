<x-layouts.app :title="'Construction · '.$project->name">
    <x-page-header :title="$project->name" :breadcrumbs="['Dashboard' => route('dashboard'), 'Construction' => route('construction.index'), $project->code => null]">
        <x-slot:actions>
            @can('construction:progress-submit', $project)
                <x-button icon="plus" data-remote-modal="{{ route('construction.progress.create', $project) }}">Submit Progress</x-button>
            @endcan
            @can('construction:labour-manage', $project)
                <x-button variant="secondary" icon="users" data-remote-modal="{{ route('labour.create', ['project' => $project->public_id]) }}" data-size="max-w-3xl">Record Labour</x-button>
            @endcan
            <x-button variant="ghost" icon="building" href="{{ route('projects.show', $project) }}">Project</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Work Package Progress" :value="App\Support\Format::percent($packageProgress)" icon="bar-chart" />
        <x-stat-card label="Stage Progress" :value="App\Support\Format::percent($stageProgress)" icon="stairs" />
        <x-stat-card label="Milestones Done" :value="$milestones->where('status', 'completed')->count().' / '.$milestones->count()" icon="check-square" />
        <x-stat-card label="Awaiting Approval" :value="$pendingUpdates + $pendingLabour" icon="clock" />
    </div>

    <div class="mb-6 mt-8 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        @foreach (['packages' => 'Work Packages', 'milestones' => 'Milestones', 'progress' => 'Progress', 'labour' => 'Labour', 'cost' => 'Cost'] as $key => $label)
            @continue($key === 'cost' && $costs === null)
            <button type="button" role="tab" class="tab" id="tab-{{ $key }}" data-tab="{{ $key }}" aria-controls="panel-{{ $key }}">{{ $label }}</button>
        @endforeach
    </div>

    <section id="panel-packages" role="tabpanel" aria-labelledby="tab-packages">
        <x-card title="Work Packages" :padded="false">
            @can('construction:manage', $project)
                <x-slot:actions><x-button size="sm" variant="secondary" icon="plus" data-remote-modal="{{ route('construction.work-packages.create', $project) }}" data-size="max-w-3xl">Add Work Package</x-button></x-slot:actions>
            @endcan
            <div data-partial-url="{{ route('construction.work-packages.index', $project) }}" data-lazy><div class="p-5"><div class="skeleton h-12 w-full"></div></div></div>
        </x-card>
    </section>

    <section id="panel-milestones" role="tabpanel" aria-labelledby="tab-milestones" hidden>
        <x-card title="Milestones" :padded="false">
            @can('construction:manage', $project)
                <x-slot:actions><x-button size="sm" variant="secondary" icon="plus" data-remote-modal="{{ route('construction.milestones.create', $project) }}">Add Milestone</x-button></x-slot:actions>
            @endcan
            <div data-partial-url="{{ route('construction.milestones.index', $project) }}" data-lazy><div class="p-5"><div class="skeleton h-12 w-full"></div></div></div>
        </x-card>
    </section>

    <section id="panel-progress" role="tabpanel" aria-labelledby="tab-progress" hidden>
        <x-card title="Progress Updates" :padded="false">
            <div data-partial-url="{{ route('construction.progress.index', $project) }}" data-lazy><div class="p-5"><div class="skeleton h-12 w-full"></div></div></div>
        </x-card>
    </section>

    <section id="panel-labour" role="tabpanel" aria-labelledby="tab-labour" hidden>
        @include('construction.labour._table', ['project' => $project])
    </section>

    @if ($costs !== null)
        <section id="panel-cost" role="tabpanel" aria-labelledby="tab-cost" hidden>
            <div data-partial-url="{{ route('construction.cost', $project) }}" data-lazy><div class="card p-5"><div class="skeleton h-24 w-full"></div></div></div>
        </section>
    @endif
</x-layouts.app>
