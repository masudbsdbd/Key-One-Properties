<x-layouts.app title="Stock">
    <x-page-header title="Stock" :breadcrumbs="['Dashboard' => route('dashboard'), 'Inventory' => null, 'Stock' => null]">
        <x-slot:actions>
            @can('materials.receive')<x-button icon="download" data-remote-modal="{{ route('stock.receive.form') }}" data-size="max-w-4xl">Receive</x-button>@endcan
            @can('materials.issue')<x-button variant="secondary" icon="upload" data-remote-modal="{{ route('stock.issue.form') }}" data-size="max-w-4xl">Issue</x-button>@endcan
            <x-button variant="ghost" icon="list" href="{{ route('stock.movements') }}">Movements</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        @if ($value !== null)<x-stat-card label="Stock Value" :value="App\Support\Format::money($value, false)" icon="bar-chart" />@endif
        <x-stat-card label="Stock Lines" :value="number_format($lines)" icon="layers" />
        <x-stat-card label="Low Stock" :value="number_format($low)" icon="alert" />
        <x-stat-card label="Lent Out" :value="number_format($borrowedOut)" icon="refresh" />
    </div>

    <x-data-table class="mt-6" :url="route('stock.table')" sort="owned" dir="desc" search="Search material" :columns="array_values(array_filter([
        ['label' => 'Material'], ['label' => 'Warehouse'], ['label' => 'Owned', 'sort' => 'owned', 'class' => 'text-right'],
        ['label' => 'Borrowed In', 'class' => 'text-right'], ['label' => 'Lent Out', 'class' => 'text-right'], ['label' => 'Physical', 'class' => 'text-right'],
        auth()->user()->can('materials.valuation.view') ? ['label' => 'Avg Cost', 'class' => 'text-right'] : null,
        auth()->user()->can('materials.valuation.view') ? ['label' => 'Value', 'sort' => 'value', 'class' => 'text-right'] : null,
    ]))">
        <x-slot:filters>
            <div class="w-56"><select name="warehouse" data-table-filter data-select data-select-url="{{ route('options.warehouses') }}" data-placeholder="All warehouses" class="form-control" aria-label="Warehouse"><option value=""></option></select></div>
            <div class="w-56"><select name="material" data-table-filter data-select data-select-url="{{ route('options.materials') }}" data-placeholder="All materials" class="form-control" aria-label="Material"><option value=""></option></select></div>
            <select name="filter" class="form-control w-36" data-table-filter aria-label="Show"><option value="">All stock</option><option value="low">Low stock</option><option value="borrowed">Borrowed</option></select>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
