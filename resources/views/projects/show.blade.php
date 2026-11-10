@php
    $user = auth()->user();
    $construction = Gate::allows('construction:view', $project);
    $tabs = array_filter([
        'overview' => 'Overview', 'stages' => 'Stages & Progress',
        'packages' => $construction ? 'Work Packages' : null,
        'flats' => Route::has('projects.buildings.index') ? 'Units' : null,
        'labour' => $construction ? 'Labour' : null,
        'materials' => $user->can('materials.view') ? 'Materials' : null,
        'purchases' => $user->can('purchases.view') ? 'Purchases' : null,
        'contractors' => $user->can('contractors.view') ? 'Contractors' : null,
        'expenses' => $user->can('expenses.view.project') ? 'Expenses' : null,
        'cost' => Gate::allows('construction:cost-view', $project) ? 'Cost' : null,
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
            <button type="button" role="tab" class="tab" id="tab-{{ $key }}" data-tab="{{ $key }}" aria-controls="panel-{{ $key }}">{{ $label }}@if ($pending[$key] ?? 0)<span class="badge badge-warning ml-1.5 h-5 px-1.5" data-tooltip="Awaiting approval">{{ $pending[$key] }}</span>@endif</button>
        @endforeach
    </div>

    @php $o = App\Modules\Projects\Services\ProjectOverview::for($project, $user); $fmt = fn ($v) => App\Support\Format::money($v, false); @endphp
    <section id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" class="space-y-6">
        <div class="grid items-start gap-6 lg:grid-cols-3">
            <x-card title="Stages" :padded="false" class="min-w-0 lg:col-span-2">
                <x-slot:actions><a href="#stages" class="text-[13px] font-medium text-accent-700 hover:underline">Stages & Progress</a></x-slot:actions>
                @if ($project->stages->isEmpty())
                    <x-empty-state icon="layers" title="No stages" />
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($project->stages->sortBy('sequence') as $stage)
                            <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1.5 px-5 py-3 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)_auto]">
                                <span class="min-w-0"><span class="block truncate text-sm font-medium text-ink-900">{{ $stage->name }}</span>
                                    <span class="block text-[12px] text-ink-500">{{ $stage->planned_end_at ? 'Due '.App\Support\Format::date($stage->planned_end_at) : 'No due date' }}</span></span>
                                <x-progress :value="$stage->progress_percent" class="order-last col-span-2 sm:order-none sm:col-span-1" />
                                <x-badge :tone="App\Models\ProjectStage::STATUS_TONES[$stage->status] ?? 'neutral'">{{ App\Models\ProjectStage::STATUSES[$stage->status] ?? $stage->status }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                    @if ($o['milestone'])
                        <p class="flex items-center gap-2 border-t border-line px-5 py-3 text-sm text-ink-700"><x-icon name="calendar-check" size="h-4 w-4 text-accent-700" />Next milestone: <span class="font-medium text-ink-900">{{ $o['milestone']->title }}</span> · {{ App\Support\Format::date($o['milestone']->planned_at) }}</p>
                    @endif
                @endif
            </x-card>

            <x-card title="Details" class="min-w-0">
                <dl class="space-y-3.5 text-sm">
                    <div class="flex gap-3"><x-icon name="tag" size="h-4 w-4 mt-0.5 shrink-0 text-ink-400" /><dt class="sr-only">Code</dt><dd class="font-medium text-ink-900">{{ $project->code }}</dd></div>
                    <div class="flex gap-3"><x-icon name="map-pin" size="h-4 w-4 mt-0.5 shrink-0 text-ink-400" /><dt class="sr-only">Location</dt><dd class="text-ink-900">{{ $project->location_text }}@if ($project->address && $project->address !== $project->location_text)<span class="block text-[13px] text-ink-500">{{ $project->address }}</span>@endif
                        @if ($project->latitude)<a href="https://www.google.com/maps?q={{ $project->latitude }},{{ $project->longitude }}" target="_blank" rel="noopener" class="block text-[13px] text-accent-700 hover:underline">{{ $project->latitude }}, {{ $project->longitude }}</a>@endif</dd></div>
                    <div class="flex gap-3"><x-icon name="calendar" size="h-4 w-4 mt-0.5 shrink-0 text-ink-400" /><dt class="sr-only">Dates</dt><dd class="text-ink-900">{{ App\Support\Format::date($project->start_date) }} → {{ App\Support\Format::date($project->target_completion_date) }}@if ($project->actual_completion_date)<span class="block text-[13px] text-success-600">Completed {{ App\Support\Format::date($project->actual_completion_date) }}</span>@endif</dd></div>
                    <div class="flex gap-3"><x-icon name="users" size="h-4 w-4 mt-0.5 shrink-0 text-ink-400" /><dt class="sr-only">Team</dt><dd class="text-ink-900">{{ \Illuminate\Support\Str::plural('member', $project->members_count, true) }} · {{ $project->media_count }} {{ \Illuminate\Support\Str::plural('photo', $project->media_count) }}</dd></div>
                    <div class="flex gap-3"><x-icon name="user" size="h-4 w-4 mt-0.5 shrink-0 text-ink-400" /><dt class="sr-only">Created</dt><dd class="text-ink-500">{{ $project->creator?->name ?? '—' }} · {{ App\Support\Format::date($project->created_at) }}</dd></div>
                </dl>
                @if ($project->description)<p class="mt-4 whitespace-pre-line border-t border-line pt-4 text-sm text-ink-700">{{ $project->description }}</p>@endif
            </x-card>
        </div>

        @php $cards = array_filter(['sales' => $o['sales'], 'cost' => $o['cost'], 'updates' => true]); @endphp
        <div @class(['grid gap-6', 'lg:grid-cols-3' => count($cards) >= 3, 'lg:grid-cols-2' => count($cards) === 2])>
            @if ($o['sales'])
                @php $s = $o['sales']; @endphp
                <x-card title="Sales & Collections" class="min-w-0">
                    <dl class="space-y-3">
                        <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Sold Value</dt><dd class="font-semibold tabular-nums text-ink-900">{{ $fmt($s['value']) }} <span class="text-[12px] font-normal text-ink-500">· {{ \Illuminate\Support\Str::plural('sale', $s['count'], true) }}</span></dd></div>
                        @if ($s['collected'] !== null)
                            <div><div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Collected</dt><dd class="font-semibold tabular-nums text-success-600">{{ $fmt($s['collected']) }}</dd></div>@if ($s['collected_pct'] !== null)<x-progress :value="$s['collected_pct']" class="mt-1.5" />@endif</div>
                            <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Receivable</dt><dd class="font-semibold tabular-nums text-ink-900">{{ $fmt($s['receivable']) }}</dd></div>
                        @endif
                        @if ($s['overdue'] !== null)
                            <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Overdue</dt><dd @class(['font-semibold tabular-nums', 'text-danger-600' => App\Support\Money::isPositive($s['overdue']), 'text-ink-900' => ! App\Support\Money::isPositive($s['overdue'])])>{{ $fmt($s['overdue']) }}</dd></div>
                        @endif
                        @if ($o['units'])<div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Unsold Stock</dt><dd class="font-medium tabular-nums text-ink-900">{{ $fmt($o['units']['available_value']) }}</dd></div>@endif
                    </dl>
                    <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-[13px] font-medium">
                        <a href="{{ route('sales.index', ['project' => $project->public_id]) }}" class="text-accent-700 hover:underline">Sales</a>
                        @can('collections.plan.view')<a href="{{ route('dues.index', ['project' => $project->public_id]) }}" class="text-accent-700 hover:underline">Dues</a>@endcan
                    </div>
                </x-card>
            @endif

            @if ($o['cost'])
                @php $c = $o['cost']; @endphp
                <x-card title="Cost" class="min-w-0">
                    <x-bars :items="collect(App\Models\ProjectCostAllocation::CATEGORIES)->mapWithKeys(fn ($label, $key) => [$label => $c['by_category'][$key] ?? '0'])->all()" tone="bg-warning-600" />
                    <dl class="mt-4 space-y-2 border-t border-line pt-3">
                        <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Spent</dt><dd class="font-semibold tabular-nums text-ink-900">{{ $fmt($c['spent']) }}</dd></div>
                        @if ($c['remaining'] !== null)<div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">{{ $c['over'] ? 'Over Budget' : 'Remaining Budget' }}</dt><dd @class(['font-semibold tabular-nums', 'text-danger-600' => $c['over'], 'text-ink-900' => ! $c['over']])>{{ $fmt(ltrim($c['remaining'], '-')) }}</dd></div>@endif
                    </dl>
                    @isset($tabs['cost'])<a href="#cost" class="mt-3 inline-block text-[13px] font-medium text-accent-700 hover:underline">Cost details</a>@endisset
                </x-card>
            @endif

            <x-card title="Latest Progress" :padded="false" class="min-w-0">
                <x-slot:actions><a href="#stages" class="text-[13px] font-medium text-accent-700 hover:underline">All updates</a></x-slot:actions>
                @forelse ($o['updates'] as $update)
                    <div class="border-b border-line px-5 py-3 last:border-0">
                        <div class="flex items-baseline justify-between gap-3"><p class="truncate text-sm font-medium text-ink-900">{{ $update->stage?->name ?? 'Project' }}</p><span class="shrink-0 text-[12px] tabular-nums text-ink-500">{{ (float) $update->previous_percent }}% → <span class="font-semibold text-ink-900">{{ (float) $update->progress_percent }}%</span></span></div>
                        @if ($update->summary)<p class="mt-0.5 line-clamp-2 text-[13px] text-ink-700">{{ $update->summary }}</p>@endif
                        <p class="mt-0.5 text-[12px] text-ink-500">{{ App\Support\Format::date($update->report_date) }} · {{ $update->recorder?->name }}</p>
                    </div>
                @empty
                    <x-empty-state icon="activity" title="No progress recorded" class="py-8" />
                @endforelse
            </x-card>
        </div>
    </section>

    <section id="panel-stages" role="tabpanel" aria-labelledby="tab-stages" hidden class="grid items-start gap-6 xl:grid-cols-[3fr_2fr]">
        <div class="space-y-6">
        <x-card title="Stages" :padded="false">
            @can('manageStages', $project)
                <x-slot:actions>
                    @can('settings.manage')<x-button size="sm" variant="ghost" icon="settings" href="{{ route('settings.edit') }}#default-stages">Default Stages</x-button>@endcan
                    <x-button size="sm" variant="secondary" icon="plus" data-remote-modal="{{ route('projects.stages.create', $project) }}">Add Stage</x-button>
                </x-slot:actions>
            @endcan
            <div data-partial-url="{{ route('projects.stages.index', $project) }}" data-stage-list data-reorder-url="{{ route('projects.stages.reorder', $project) }}">
                @include('projects.stages._list')
            </div>
        </x-card>
        @if ($construction)
            <x-card title="Milestones" :padded="false">
                @can('construction:manage', $project)
                    <x-slot:actions><x-button size="sm" variant="secondary" icon="plus" data-remote-modal="{{ route('construction.milestones.create', $project) }}">Add Milestone</x-button></x-slot:actions>
                @endcan
                <div data-partial-url="{{ route('construction.milestones.index', $project) }}" data-lazy><div class="p-5"><div class="skeleton h-12 w-full"></div></div></div>
            </x-card>
        @endif
        </div>
        <x-card title="Progress Updates" :padded="false">
            <div data-partial-url="{{ route('projects.progress.index', $project) }}" data-lazy>
                <div class="space-y-3 p-5"><div class="skeleton h-12 w-full"></div><div class="skeleton h-12 w-full"></div></div>
            </div>
        </x-card>
    </section>

    @if (Route::has('projects.buildings.index'))
        <section id="panel-flats" role="tabpanel" aria-labelledby="tab-flats" hidden class="space-y-6">
            @include('flats.partials.project-buildings', ['project' => $project])
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

    @isset($tabs['cost'])
        <section id="panel-cost" role="tabpanel" aria-labelledby="tab-cost" hidden>
            @include('construction.cost._breakdown', ['project' => $project])
        </section>
    @endisset
    @isset($tabs['packages'])
        <section id="panel-packages" role="tabpanel" aria-labelledby="tab-packages" hidden class="space-y-6">
            <x-card title="Work Packages" :padded="false">
                @can('construction:manage', $project)
                    <x-slot:actions><x-button size="sm" variant="secondary" icon="plus" data-remote-modal="{{ route('construction.work-packages.create', $project) }}" data-size="max-w-3xl">Add Work Package</x-button></x-slot:actions>
                @endcan
                <div data-partial-url="{{ route('construction.work-packages.index', $project) }}" data-lazy><div class="p-5"><div class="skeleton h-12 w-full"></div></div></div>
            </x-card>
            <x-card title="Work Package Updates" :padded="false">
                @can('construction:progress-submit', $project)
                    <x-slot:actions><x-button size="sm" variant="secondary" icon="plus" data-remote-modal="{{ route('construction.progress.create', $project) }}">Submit Progress</x-button></x-slot:actions>
                @endcan
                <div data-partial-url="{{ route('construction.progress.index', $project) }}" data-lazy><div class="p-5"><div class="skeleton h-12 w-full"></div></div></div>
            </x-card>
        </section>
    @endisset
    @isset($tabs['labour'])
        <section id="panel-labour" role="tabpanel" aria-labelledby="tab-labour" hidden>
            @include('construction.labour._table', ['project' => $project])
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
                <x-slot:toolbar>@can('purchases.create')<x-button icon="plus" data-remote-modal="{{ route('purchase-orders.create', ['project' => $project->public_id]) }}" data-size="max-w-5xl">Purchase Order</x-button>@endcan</x-slot:toolbar>
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
