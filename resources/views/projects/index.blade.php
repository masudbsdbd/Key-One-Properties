<x-layouts.app title="Projects">
    <x-page-header title="Projects" :breadcrumbs="['Dashboard' => route('dashboard'), 'Projects' => null]">
        @can('create', App\Models\Project::class)
            <x-slot:actions>
                <x-button icon="plus" data-remote-modal="{{ route('projects.create') }}" data-size="max-w-3xl" data-auto-create>New Project</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-data-table :url="route('projects.table')" sort="name" search="Search name, code or location" data-view="table" data-project-table :columns="[
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
            <input type="hidden" name="view" value="table" data-table-filter data-view-input>
            <div class="inline-flex rounded-control border border-line-strong p-0.5" role="group" aria-label="Layout">
                <button type="button" class="btn btn-sm btn-ghost h-8 aria-pressed:bg-accent-50 aria-pressed:text-accent-700" data-view-toggle="table" aria-pressed="true" aria-label="Table view"><x-icon name="list" size="h-4 w-4" /></button>
                <button type="button" class="btn btn-sm btn-ghost h-8 aria-pressed:bg-accent-50 aria-pressed:text-accent-700" data-view-toggle="cards" aria-pressed="false" aria-label="Card view"><x-icon name="grid" size="h-4 w-4" /></button>
            </div>
        </x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
