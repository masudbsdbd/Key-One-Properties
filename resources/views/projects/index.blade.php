<x-layouts.app title="Projects">
    <x-page-header title="Projects" :breadcrumbs="['Dashboard' => route('dashboard'), 'Projects' => null]">
        @can('create', App\Models\Project::class)
            <x-slot:actions>
                <x-button icon="plus" data-remote-modal="{{ route('projects.create') }}" data-size="max-w-3xl" data-auto-create>New Project</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-data-table summary :url="route('projects.table')" sort="name" search="Search name, code or location" view-key="projects.view" :view="App\Support\Preferences::get(auth()->user(), 'projects.view')" :columns="[
        ['label' => 'Project', 'sort' => 'name'],
        ['label' => 'Location'],
        ['label' => 'Status'],
        ['label' => 'Progress', 'class' => 'w-48'],
        ['label' => 'Start', 'sort' => 'start'],
        ['label' => 'Target', 'sort' => 'target'],
        ['label' => 'Budget', 'sort' => 'budget', 'class' => 'text-right'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status">
                <option value="">All statuses</option>
                @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
            @can('projects.archive')
                <select name="archived" class="form-control w-36" data-table-filter aria-label="Archived">
                    <option value="">Current</option>
                    <option value="1">Archived</option>
                </select>
            @endcan
        </x-slot:filters>
        <x-slot:toolbar>
            @can('projects.export')<x-export-menu :url="route('projects.export')" />@endcan
        </x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
