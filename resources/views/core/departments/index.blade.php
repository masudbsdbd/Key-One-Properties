<x-layouts.app title="Departments">
    <x-page-header title="Departments" :breadcrumbs="['Dashboard' => route('dashboard'), 'Departments' => null]">
        @can('departments.manage')
            <x-slot:actions>
                <x-button icon="plus" data-remote-modal="{{ route('departments.create') }}" data-auto-create>New Department</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-data-table :url="route('departments.table')" sort="name" search="Search departments" :columns="[
        ['label' => 'Department', 'sort' => 'name'],
        ['label' => 'Members', 'sort' => 'members'],
        ['label' => 'Status'],
        ['label' => '', 'class' => 'w-24'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status">
                <option value="">All statuses</option>
                @foreach (App\Models\Department::STATUSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
