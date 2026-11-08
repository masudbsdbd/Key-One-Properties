<x-layouts.app title="Roles & Permissions">
    <x-page-header title="Roles & Permissions" :breadcrumbs="['Dashboard' => route('dashboard'), 'Roles & Permissions' => null]">
        @can('roles.manage')
            <x-slot:actions>
                <x-button icon="plus" data-remote-modal="{{ route('roles.create') }}" data-size="max-w-4xl" data-auto-create>New Role</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-data-table :url="route('roles.table')" sort="name" search="Search roles" :columns="[
        ['label' => 'Role', 'sort' => 'name'],
        ['label' => 'Permissions'],
        ['label' => 'Users', 'sort' => 'users'],
        ['label' => '', 'class' => 'w-24'],
    ]" />
</x-layouts.app>
