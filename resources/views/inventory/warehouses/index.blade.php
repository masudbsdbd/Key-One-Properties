<x-layouts.app title="Warehouses">
    <x-page-header title="Warehouses" :breadcrumbs="['Dashboard' => route('dashboard'), 'Inventory' => route('stock.index'), 'Warehouses' => null]">
        @can('materials.manage')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('warehouses.create') }}" data-auto-create>New Warehouse</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table :url="route('warehouses.table')" sort="name" search="Search code or name" :columns="[
        ['label' => 'Code', 'sort' => 'code'], ['label' => 'Warehouse', 'sort' => 'name'], ['label' => 'Belongs To'], ['label' => 'Items', 'class' => 'text-right'],
        ['label' => 'Stock Value', 'class' => 'text-right'], ['label' => 'Status'], ['label' => '', 'class' => 'w-16'],
    ]">
        <x-slot:toolbar>@can('materials.export')<x-export-menu :url="route('warehouses.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
