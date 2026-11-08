<x-layouts.app :title="$project->name">
    <x-page-header :title="$project->name" :breadcrumbs="['Dashboard' => route('dashboard'), 'Projects' => route('projects.index'), $project->name => null]">
        <x-slot:actions>
            @can('updateProgress', $project)
                <x-button icon="plus" data-remote-modal="{{ route('projects.progress.create', $project) }}">Record Progress</x-button>
            @endcan
            @can('update', $project)
                <x-button variant="secondary" icon="edit" data-remote-modal="{{ route('projects.edit', $project) }}" data-size="max-w-3xl">Edit</x-button>
            @endcan
            @can('archive', $project)
                <x-button variant="ghost" icon="archive" data-action="delete" data-url="{{ route('projects.destroy', $project) }}" data-confirm="Archive {{ $project->name }}?" data-confirm-text="Archive" aria-label="Archive project" />
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div data-partial-url="{{ route('projects.summary', $project) }}">
        @include('projects.partials._summary')
    </div>

    <div class="mb-6 mt-8 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        @foreach (['overview' => 'Overview', 'stages' => 'Stages & Progress', 'buildings' => 'Buildings', 'flats' => 'Units', 'gallery' => 'Gallery', 'team' => 'Team', 'activity' => 'Activity'] as $key => $label)
            @continue(in_array($key, ['buildings', 'flats']) && ! Route::has('projects.buildings.index'))
            <button type="button" role="tab" class="tab" id="tab-{{ $key }}" data-tab="{{ $key }}" aria-controls="panel-{{ $key }}">{{ $label }}</button>
        @endforeach
    </div>

    <section id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" class="grid gap-6 lg:grid-cols-3">
        <x-card title="Details" class="lg:col-span-2">
            <dl class="grid gap-5 sm:grid-cols-2">
                <div><dt class="detail-label">Code</dt><dd class="detail-value">{{ $project->code }}</dd></div>
                <div><dt class="detail-label">Location</dt><dd class="detail-value">{{ $project->location_text }}</dd></div>
                <div class="sm:col-span-2"><dt class="detail-label">Address</dt><dd class="detail-value">{{ $project->address ?: '—' }}</dd></div>
                <div><dt class="detail-label">Start Date</dt><dd class="detail-value">{{ App\Support\Format::date($project->start_date) }}</dd></div>
                <div><dt class="detail-label">Target Completion</dt><dd class="detail-value">{{ App\Support\Format::date($project->target_completion_date) }}</dd></div>
                <div><dt class="detail-label">Actual Completion</dt><dd class="detail-value">{{ App\Support\Format::date($project->actual_completion_date) }}</dd></div>
                <div><dt class="detail-label">Coordinates</dt><dd class="detail-value">{{ $project->latitude ? $project->latitude.', '.$project->longitude : '—' }}</dd></div>
                <div><dt class="detail-label">Created By</dt><dd class="detail-value">{{ $project->creator?->name ?? '—' }}</dd></div>
                <div><dt class="detail-label">Created</dt><dd class="detail-value">{{ App\Support\Format::datetime($project->created_at) }}</dd></div>
                @if ($project->description)
                    <div class="sm:col-span-2"><dt class="detail-label">Description</dt><dd class="detail-value whitespace-pre-line">{{ $project->description }}</dd></div>
                @endif
            </dl>
        </x-card>
        <x-card title="Budget">
            <dl class="space-y-4">
                <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Estimated</dt><dd class="font-semibold tabular-nums text-ink-900">{{ App\Support\Format::money($project->estimated_budget) }}</dd></div>
                <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Actual cost</dt><dd><x-badge>Phase 2</x-badge></dd></div>
            </dl>
        </x-card>
    </section>

    <section id="panel-stages" role="tabpanel" aria-labelledby="tab-stages" hidden class="grid gap-6 xl:grid-cols-[3fr_2fr]">
        <x-card title="Stages" :padded="false">
            @can('manageStages', $project)
                <x-slot:actions>
                    <x-button size="sm" variant="secondary" icon="plus" data-remote-modal="{{ route('projects.stages.create', $project) }}">Add Stage</x-button>
                </x-slot:actions>
            @endcan
            <div data-partial-url="{{ route('projects.stages.index', $project) }}" data-stage-list data-reorder-url="{{ route('projects.stages.reorder', $project) }}">
                @include('projects.stages._list')
            </div>
        </x-card>
        <x-card title="Progress Updates" :padded="false">
            <div data-partial-url="{{ route('projects.progress.index', $project) }}" data-lazy>
                <div class="space-y-3 p-5"><div class="skeleton h-12 w-full"></div><div class="skeleton h-12 w-full"></div></div>
            </div>
        </x-card>
    </section>

    @if (Route::has('projects.buildings.index'))
        <section id="panel-buildings" role="tabpanel" aria-labelledby="tab-buildings" hidden>
            @include('flats.partials.project-buildings', ['project' => $project])
        </section>
        <section id="panel-flats" role="tabpanel" aria-labelledby="tab-flats" hidden>
            @include('flats.partials.project-flats', ['project' => $project])
        </section>
    @endif

    <section id="panel-gallery" role="tabpanel" aria-labelledby="tab-gallery" hidden>
        <x-card title="Gallery">
            @can('manageMedia', $project)
                <x-slot:actions>
                    <form method="POST" action="{{ route('projects.media.store', $project) }}" enctype="multipart/form-data" data-ajax-form data-reset data-refresh-partials class="flex items-center gap-2" data-upload-form>
                        @csrf
                        <select name="visibility" class="form-control h-9 w-40 text-[13px]" aria-label="Visibility">
                            @foreach (App\Models\ProjectMedia::VISIBILITIES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        <label class="btn btn-primary btn-sm cursor-pointer">
                            <x-icon name="upload" size="h-4 w-4" />Upload
                            <input type="file" name="images[]" accept="image/png,image/jpeg,image/webp" multiple class="sr-only" data-auto-submit>
                        </label>
                        <span data-field><span class="form-error mt-0" data-error hidden></span><input type="hidden" name="images" disabled></span>
                        <span class="form-error mt-0" data-form-error hidden></span>
                    </form>
                </x-slot:actions>
            @endcan
            <div data-partial-url="{{ route('projects.media.index', $project) }}" data-lazy>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4"><div class="skeleton aspect-[4/3]"></div><div class="skeleton aspect-[4/3]"></div></div>
            </div>
        </x-card>
    </section>

    <section id="panel-team" role="tabpanel" aria-labelledby="tab-team" hidden>
        <x-card title="Team" :padded="false">
            @can('manageMembers', $project)
                <x-slot:actions>
                    <x-button size="sm" variant="secondary" icon="user-plus" data-remote-modal="{{ route('projects.members.create', $project) }}">Add Members</x-button>
                </x-slot:actions>
            @endcan
            <div data-partial-url="{{ route('projects.members.index', $project) }}" data-lazy>
                <div class="space-y-3 p-5"><div class="skeleton h-10 w-full"></div><div class="skeleton h-10 w-full"></div></div>
            </div>
        </x-card>
    </section>

    <section id="panel-activity" role="tabpanel" aria-labelledby="tab-activity" hidden>
        <x-card title="Activity" :padded="false">
            <div data-partial-url="{{ route('projects.activity', $project) }}" data-lazy>
                <div class="space-y-3 p-5"><div class="skeleton h-10 w-full"></div><div class="skeleton h-10 w-full"></div></div>
            </div>
        </x-card>
    </section>
</x-layouts.app>
