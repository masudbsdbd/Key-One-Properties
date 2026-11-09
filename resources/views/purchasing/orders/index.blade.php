<x-layouts.app title="Purchase Orders">
    <x-page-header title="Purchase Orders" :breadcrumbs="['Dashboard' => route('dashboard'), 'Purchase Orders' => null]">
        @can('purchases.create')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('purchase-orders.create') }}" data-size="max-w-5xl" data-auto-create>New Purchase Order</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table summary data-table-history :url="route('purchase-orders.table')" sort="date" dir="desc" search="Search PO number" :columns="[
        ['label' => 'Order', 'sort' => 'date'], ['label' => 'Supplier'], ['label' => 'Warehouse'], ['label' => 'Total', 'class' => 'text-right', 'sort' => 'total'], ['label' => 'Received'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-44" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\PurchaseOrder::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-56"><select name="supplier" class="form-control" data-table-filter data-select data-select-url="{{ route('options.suppliers') }}" data-placeholder="All suppliers" aria-label="Supplier"><option value=""></option></select></div>
            <div class="w-52"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option></select></div>
            <select name="unbilled" class="form-control w-48" data-table-filter aria-label="Billing"><option value="">All billing</option>@foreach (App\Modules\Purchasing\Services\Unbilled::FILTERS as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <x-period />
            <input type="hidden" name="late" data-table-filter>
        </x-slot:filters>
        <x-slot:toolbar>@can('purchases.export')<x-export-menu :url="route('purchase-orders.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
