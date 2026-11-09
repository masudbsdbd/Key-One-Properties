<x-layouts.app title="Sales">
    <x-page-header title="Sales" :breadcrumbs="['Dashboard' => route('dashboard'), 'Sales' => route('sales.overview'), 'Sales List' => null]">
        @can('sales.sale.create')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('sales.create') }}" data-size="max-w-3xl" data-auto-create>New Sale</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table summary data-table-history :url="route('sales.table')" sort="date" dir="desc" search="Search sale, unit or customer" :columns="[
        ['label' => 'Sale', 'sort' => 'date'], ['label' => 'Unit'], ['label' => 'Customer'], ['label' => 'Contract Value', 'class' => 'text-right', 'sort' => 'value'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-48" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\FlatSale::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-56"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option></select></div>
            <x-period />
        </x-slot:filters>
        <x-slot:toolbar>@can('sales.export')<x-export-menu :url="route('sales.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
