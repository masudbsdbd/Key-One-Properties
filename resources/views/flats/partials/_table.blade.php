@php($scope = $project ? 'p' : 'all')
<x-data-table :url="route('flats.table', $project ? ['project' => $project->public_id] : [])" sort="flat_no" search="Search unit number" :columns="array_values(array_filter([
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
