@php($scope = $project ? 'p' : 'all')
<x-data-table :url="route('flats.table', $project ? ['project' => $project->public_id] : [])" sort="flat_no" search="Search unit number" view-key="flats.view" :view="App\Support\Preferences::get(auth()->user(), 'flats.view')" :selectable="auth()->user()->can('flats.change_status')" :columns="array_values(array_filter([
    ['label' => 'Unit', 'sort' => 'flat_no'],
    $project ? null : ['label' => 'Project'],
    ['label' => 'Building / Floor', 'sort' => 'floor'],
    ['label' => 'Type'],
    ['label' => 'Area', 'sort' => 'area', 'class' => 'text-right'],
    ['label' => 'Price', 'sort' => 'price', 'class' => 'text-right'],
    ['label' => 'Status', 'sort' => 'status'],
    ['label' => '', 'class' => 'w-40'],
]))">
    <x-slot:filters>
        @unless ($project)
            <div class="w-56">
                <select id="{{ $scope }}-filter-project" name="project" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" class="form-control" aria-label="Project"><option value=""></option></select>
            </div>
        @endunless
        <div class="w-48">
            <select id="{{ $scope }}-filter-building" name="building" data-table-filter data-select data-select-url="{{ route('options.buildings') }}"
                data-depends-on="{{ $project ? '#'.$scope.'-filter-project-fixed' : '#'.$scope.'-filter-project' }}" data-depends-param="project" data-placeholder="All buildings" class="form-control" aria-label="Building"><option value=""></option></select>
            @if ($project)<input type="hidden" id="{{ $scope }}-filter-project-fixed" value="{{ $project->public_id }}">@endif
        </div>
        <div class="w-44">
            <select name="floor" data-table-filter data-select data-select-url="{{ route('options.floors') }}" data-depends-on="#{{ $scope }}-filter-building" data-depends-param="building" data-placeholder="All floors" class="form-control" aria-label="Floor"><option value=""></option></select>
        </div>
        <select name="status" class="form-control w-36" data-table-filter aria-label="Status">
            <option value="">All statuses</option>
            @foreach (App\Models\Flat::STATUSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
        <div class="w-36">
            <select name="type" class="form-control" data-table-filter data-select data-placeholder="All types" aria-label="Type">
                <option value=""></option>
                @foreach (App\Models\Flat::TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </div>
    </x-slot:filters>
    <x-slot:bulk>
        <x-button size="sm" icon="check" data-bulk-action data-url="{{ route('flats.bulk-status') }}" data-status="available" data-confirm="Release the selected units for sale?" data-confirm-text="Release" data-tone="warning">Release for Sale</x-button>
        <div class="relative">
            <x-button size="sm" variant="secondary" data-menu-button aria-controls="{{ $scope }}-bulk-status" aria-expanded="false">Set Status<x-icon name="chevron-down" size="h-4 w-4" /></x-button>
            <div id="{{ $scope }}-bulk-status" data-menu hidden class="absolute right-0 z-20 mt-2 w-48 rounded-card border border-line bg-surface p-1.5 shadow-raised">
                @foreach (['not_released', 'unavailable', 'maintenance'] as $status)
                    <button type="button" class="flex w-full items-center rounded-lg px-3 py-2 text-left text-sm text-ink-700 hover:bg-surface-muted" data-bulk-action data-url="{{ route('flats.bulk-status') }}" data-status="{{ $status }}">{{ App\Models\Flat::STATUSES[$status] }}</button>
                @endforeach
            </div>
        </div>
    </x-slot:bulk>
    <x-slot:stats><div class="h-[74px] animate-pulse rounded-card bg-surface-muted"></div></x-slot:stats>
    <x-slot:toolbar>
        @can('flats.export')<x-export-menu :url="route('flats.export', $project ? ['project' => $project->public_id] : [])" />@endcan
        @if ($project)
            @can('bulkCreateIn', [App\Models\Flat::class, $project])
                <x-button size="sm" variant="secondary" icon="layers" data-remote-modal="{{ route('projects.flats.bulk', $project) }}" data-size="max-w-5xl">Bulk Create</x-button>
            @endcan
            @can('createIn', [App\Models\Flat::class, $project])
                <x-button size="sm" icon="plus" data-remote-modal="{{ route('flats.create', ['project' => $project->public_id]) }}" data-size="max-w-3xl">New Unit</x-button>
            @endcan
        @endif
    </x-slot:toolbar>
</x-data-table>
