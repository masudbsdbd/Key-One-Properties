<x-layouts.app title="Transfers">
    <x-page-header title="Material Transfers" :breadcrumbs="['Dashboard' => route('dashboard'), 'Inventory' => route('stock.index'), 'Transfers' => null]">
        @can('materials.transfer.request')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('transfers.create') }}" data-size="max-w-4xl" data-auto-create>New Transfer</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table summary data-table-history :url="route('transfers.table')" sort="date" dir="desc" search="Search transfer number" :columns="[
        ['label' => 'Transfer', 'sort' => 'date'], ['label' => 'From'], ['label' => 'To'], ['label' => 'Type'], ['label' => 'Value', 'class' => 'text-right'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\MaterialTransfer::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <select name="kind" class="form-control w-44" data-table-filter aria-label="Type"><option value="">All types</option>@foreach (App\Models\MaterialTransfer::KINDS as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <x-period />
        </x-slot:filters>
        <x-slot:toolbar>@can('materials.export')<x-export-menu :url="route('transfers.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
