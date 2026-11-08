<x-layouts.app title="Borrows">
    <x-page-header title="Material Borrows" :breadcrumbs="['Dashboard' => route('dashboard'), 'Inventory' => route('stock.index'), 'Borrows' => null]">
        @can('materials.borrow.request')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('borrows.create') }}" data-size="max-w-4xl" data-auto-create>Request Borrow</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table :url="route('borrows.table')" sort="date" dir="desc" search="Search borrow number" :columns="[
        ['label' => 'Borrow', 'sort' => 'date'], ['label' => 'From'], ['label' => 'To'], ['label' => 'Outstanding'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <div class="w-44">
                <select name="status" class="form-control" data-table-filter data-select data-placeholder="All statuses" aria-label="Status">
                    <option value=""></option><option value="open">Open (outstanding)</option>
                    @foreach (App\Models\MaterialBorrow::STATUSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
