<x-layouts.app title="Materials">
    <x-page-header title="Materials" :breadcrumbs="['Dashboard' => route('dashboard'), 'Inventory' => route('stock.index'), 'Materials' => null]">
        @can('materials.manage')
            <x-slot:actions>
                <x-button icon="plus" data-remote-modal="{{ route('materials.create') }}" data-auto-create>New Material</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="mb-6 flex gap-6 border-b border-line" role="tablist">
        <button type="button" role="tab" class="tab" id="tab-materials" data-tab="materials" aria-controls="panel-materials">Materials</button>
        <button type="button" role="tab" class="tab" id="tab-catalog" data-tab="catalog" aria-controls="panel-catalog">Categories & Units</button>
    </div>

    <section id="panel-materials" role="tabpanel" aria-labelledby="tab-materials">
        <x-data-table :url="route('materials.table')" sort="name" search="Search SKU or name" :columns="[
            ['label' => 'SKU', 'sort' => 'sku'], ['label' => 'Material', 'sort' => 'name'], ['label' => 'Category'], ['label' => 'Unit'],
            ['label' => 'Reorder Level', 'class' => 'text-right'], ['label' => 'Owned Stock', 'class' => 'text-right'], ['label' => 'Status'], ['label' => '', 'class' => 'w-24'],
        ]">
            <x-slot:filters>
                <div class="w-48"><select name="category" class="form-control" data-table-filter data-select data-placeholder="All categories" aria-label="Category"><option value=""></option>@foreach ($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                <select name="active" class="form-control w-32" data-table-filter aria-label="Status"><option value="">All</option><option value="1">Active</option><option value="0">Inactive</option></select>
            </x-slot:filters>
        <x-slot:toolbar>@can('materials.export')<x-export-menu :url="route('materials.export')" />@endcan</x-slot:toolbar>
        </x-data-table>
    </section>

    <section id="panel-catalog" role="tabpanel" aria-labelledby="tab-catalog" hidden>
        <div data-partial-url="{{ route('materials.catalog') }}" data-lazy><div class="card p-5"><div class="skeleton h-24 w-full"></div></div></div>
    </section>
</x-layouts.app>
