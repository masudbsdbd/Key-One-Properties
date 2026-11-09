<x-layouts.app title="Stock Movements">
    <x-page-header title="Stock Movements" :breadcrumbs="['Dashboard' => route('dashboard'), 'Stock' => route('stock.index'), 'Movements' => null]">
    </x-page-header>
    <x-data-table :url="route('stock.movements.table')" sort="date" dir="desc" search="Search movement number" :columns="array_values(array_filter([
        ['label' => 'Date', 'sort' => 'date'], ['label' => 'Movement'], ['label' => 'Material'], ['label' => 'From → To'], ['label' => 'Qty', 'class' => 'text-right'],
        auth()->user()->can('materials.valuation.view') ? ['label' => 'Value', 'class' => 'text-right'] : null, ['label' => 'Document'], ['label' => '', 'class' => 'w-24'],
    ]))">
        <x-slot:filters>
            <div class="w-56"><select name="warehouse" data-table-filter data-select data-select-url="{{ route('options.warehouses') }}" data-placeholder="All warehouses" class="form-control" aria-label="Warehouse"><option value=""></option></select></div>
            <div class="w-56"><select name="material" data-table-filter data-select data-select-url="{{ route('options.materials') }}" data-placeholder="All materials" class="form-control" aria-label="Material"><option value=""></option></select></div>
            <div class="w-44"><select name="type" class="form-control" data-table-filter data-select data-placeholder="All types" aria-label="Type"><option value=""></option>@foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        </x-slot:filters>
        <x-slot:toolbar>@can('materials.export')<x-export-menu :url="route('stock.movements.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
