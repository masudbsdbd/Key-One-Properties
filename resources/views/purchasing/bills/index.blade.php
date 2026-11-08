<x-layouts.app title="Supplier Bills">
    <x-page-header title="Supplier Bills" :breadcrumbs="['Dashboard' => route('dashboard'), 'Supplier Bills' => null]">
        @can('purchases.bill.manage')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('supplier-bills.create') }}" data-size="max-w-4xl" data-auto-create>New Bill</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table :url="route('supplier-bills.table')" sort="date" dir="desc" search="Search bill or invoice number" :columns="[
        ['label' => 'Bill', 'sort' => 'date'], ['label' => 'Supplier'], ['label' => 'Due', 'sort' => 'due'], ['label' => 'Total', 'class' => 'text-right', 'sort' => 'total'], ['label' => 'Outstanding', 'class' => 'text-right'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\SupplierBill::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-56"><select name="supplier" class="form-control" data-table-filter data-select data-select-url="{{ route('options.suppliers') }}" data-placeholder="All suppliers" aria-label="Supplier"><option value=""></option></select></div>
        </x-slot:filters>
        <x-slot:toolbar>@can('purchases.export')<x-export-menu :url="route('supplier-bills.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
