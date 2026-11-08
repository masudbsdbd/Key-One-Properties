<x-layouts.app title="Users">
    <x-page-header title="Users" :breadcrumbs="['Dashboard' => route('dashboard'), 'Users' => null]">
        @can('create', App\Models\User::class)
            <x-slot:actions>
                <x-button icon="user-plus" data-remote-modal="{{ route('users.create') }}" data-auto-create>New User</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-data-table :url="route('users.table')" sort="name" search="Search name, email or mobile" :columns="[
        ['label' => 'Name', 'sort' => 'name'],
        ['label' => 'Mobile'],
        ['label' => 'Role'],
        ['label' => 'Departments'],
        ['label' => 'Status'],
        ['label' => 'Last sign-in', 'sort' => 'last_login'],
        ['label' => '', 'class' => 'w-24'],
    ]">
        <x-slot:filters>
            <select name="role" class="form-control w-44" data-table-filter aria-label="Role">
                <option value="">All roles</option>
                @foreach ($roles as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status">
                <option value="">All statuses</option>
                @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
