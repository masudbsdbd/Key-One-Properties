<x-layouts.app title="Purchase Orders">
    <x-page-header title="Purchase Orders" :breadcrumbs="['Dashboard' => route('dashboard'), 'Purchase Orders' => null]">
        @can('purchases.create')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('purchase-orders.create') }}" data-size="max-w-5xl" data-auto-create>New Purchase Order</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table :url="route('purchase-orders.table')" sort="date" dir="desc" search="Search PO number" :columns="[
        ['label' => 'Order', 'sort' => 'date'], ['label' => 'Supplier'], ['label' => 'Warehouse'], ['label' => 'Total', 'class' => 'text-right', 'sort' => 'total'], ['label' => 'Received'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-44" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\PurchaseOrder::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-56"><select name="supplier" class="form-control" data-table-filter data-select data-select-url="{{ route('options.suppliers') }}" data-placeholder="All suppliers" aria-label="Supplier"><option value=""></option></select></div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
