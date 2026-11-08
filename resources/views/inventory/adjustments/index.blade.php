<x-layouts.app title="Stock Adjustments">
    <x-page-header title="Stock Adjustments" :breadcrumbs="['Dashboard' => route('dashboard'), 'Inventory' => route('stock.index'), 'Adjustments' => null]">
        @can('materials.adjust.request')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('adjustments.create') }}" data-size="max-w-4xl" data-auto-create>New Adjustment</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table :url="route('adjustments.table')" sort="date" dir="desc" search="Search number or reason" :columns="[
        ['label' => 'Adjustment'], ['label' => 'Warehouse'], ['label' => 'Lines'], ['label' => 'Reason'], ['label' => 'Status'], ['label' => '', 'class' => 'w-48'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option><option value="submitted">Submitted</option><option value="posted">Posted</option><option value="rejected">Rejected</option></select>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
