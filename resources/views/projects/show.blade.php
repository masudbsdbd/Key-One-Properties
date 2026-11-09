@php
    $user = auth()->user();
    $tabs = array_filter([
        'overview' => 'Overview', 'stages' => 'Stages & Progress',
        'buildings' => Route::has('projects.buildings.index') ? 'Buildings' : null, 'flats' => Route::has('projects.buildings.index') ? 'Units' : null,
        'construction' => Gate::allows('construction:cost-view', $project) ? 'Cost' : null,
        'materials' => $user->can('materials.view') ? 'Materials' : null,
        'purchases' => $user->can('purchases.view') ? 'Purchases' : null,
        'contractors' => $user->can('contractors.view') ? 'Contractors' : null,
        'expenses' => $user->can('expenses.view.project') ? 'Expenses' : null,
        'gallery' => 'Gallery', 'team' => 'Team', 'activity' => 'Activity',
    ]);
@endphp
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
        @foreach ($tabs as $key => $label)
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

    @isset($tabs['construction'])
        <section id="panel-construction" role="tabpanel" aria-labelledby="tab-construction" hidden>
            <div data-partial-url="{{ route('construction.cost', $project) }}" data-lazy><div class="card p-5"><div class="skeleton h-24 w-full"></div></div></div>
        </section>
    @endisset
    @isset($tabs['materials'])
        <section id="panel-materials" role="tabpanel" aria-labelledby="tab-materials" hidden>
            <x-data-table :url="route('stock.table', ['project' => $project->public_id])" sort="owned" dir="desc" search="Search material" :columns="array_values(array_filter([
                ['label' => 'Material'], ['label' => 'Warehouse'], ['label' => 'Owned', 'sort' => 'owned', 'class' => 'text-right'],
                ['label' => 'Borrowed In', 'class' => 'text-right'], ['label' => 'Lent Out', 'class' => 'text-right'], ['label' => 'Physical', 'class' => 'text-right'],
                $user->can('materials.valuation.view') ? ['label' => 'Avg Cost', 'class' => 'text-right'] : null,
                $user->can('materials.valuation.view') ? ['label' => 'Value', 'sort' => 'value', 'class' => 'text-right'] : null,
            ]))" />
        </section>
    @endisset
    @isset($tabs['purchases'])
        <section id="panel-purchases" role="tabpanel" aria-labelledby="tab-purchases" hidden>
            <x-data-table :url="route('purchase-orders.table', ['project' => $project->public_id])" sort="date" dir="desc" search="Search PO number" :columns="[
                ['label' => 'Order', 'sort' => 'date'], ['label' => 'Supplier'], ['label' => 'Warehouse'], ['label' => 'Total', 'class' => 'text-right', 'sort' => 'total'], ['label' => 'Received'], ['label' => 'Status'],
            ]">
                <x-slot:toolbar>@can('purchases.create')<x-button icon="plus" data-remote-modal="{{ route('purchase-orders.create') }}" data-size="max-w-5xl">Purchase Order</x-button>@endcan</x-slot:toolbar>
            </x-data-table>
        </section>
    @endisset
    @isset($tabs['contractors'])
        <section id="panel-contractors" role="tabpanel" aria-labelledby="tab-contractors" hidden>
            <x-data-table :url="route('work-orders.table', ['project' => $project->public_id])" sort="date" dir="desc" search="Search work order" :columns="[
                ['label' => 'Work Order', 'sort' => 'date'], ['label' => 'Contractor'], ['label' => 'Project'], ['label' => 'Contract', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Progress'], ['label' => 'Status'],
            ]">
                <x-slot:toolbar>@can('contractors.workorder.create')<x-button icon="plus" data-remote-modal="{{ route('work-orders.create', ['project' => $project->public_id]) }}" data-size="max-w-3xl">Work Order</x-button>@endcan</x-slot:toolbar>
            </x-data-table>
        </section>
    @endisset
    @isset($tabs['expenses'])
        <section id="panel-expenses" role="tabpanel" aria-labelledby="tab-expenses" hidden>
            <x-data-table :url="route('expenses.table', ['project' => $project->public_id])" sort="date" dir="desc" search="Search number, description or payee" :columns="[
                ['label' => 'Expense', 'sort' => 'date'], ['label' => 'Category'], ['label' => 'Scope'], ['label' => 'Payee'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'],
            ]">
                <x-slot:filters>
                    <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\Expense::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                </x-slot:filters>
                <x-slot:toolbar>@can('expenses.create.project')<x-button icon="plus" data-remote-modal="{{ route('expenses.create', ['project' => $project->public_id]) }}" data-size="max-w-3xl">Expense</x-button>@endcan</x-slot:toolbar>
            </x-data-table>
        </section>
    @endisset

    <section id="panel-activity" role="tabpanel" aria-labelledby="tab-activity" hidden>
        <x-card title="Activity" :padded="false">
            <div data-partial-url="{{ route('projects.activity', $project) }}" data-lazy>
                <div class="space-y-3 p-5"><div class="skeleton h-10 w-full"></div><div class="skeleton h-10 w-full"></div></div>
            </div>
        </x-card>
    </section>
</x-layouts.app>
