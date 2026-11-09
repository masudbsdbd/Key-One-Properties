<x-layouts.app title="Supplier Payments">
    <x-page-header title="Supplier Payments" :breadcrumbs="['Dashboard' => route('dashboard'), 'Supplier Payments' => null]">
        @can('purchases.payment.create')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('supplier-payments.create') }}" data-size="max-w-3xl" data-auto-create>New Payment</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table :url="route('supplier-payments.table')" sort="date" dir="desc" search="Search payment number or reference" :columns="[
        ['label' => 'Payment', 'sort' => 'date'], ['label' => 'Supplier'], ['label' => 'Method'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\SupplierPayment::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-56"><select name="supplier" class="form-control" data-table-filter data-select data-select-url="{{ route('options.suppliers') }}" data-placeholder="All suppliers" aria-label="Supplier"><option value=""></option></select></div>
        </x-slot:filters>
        <x-slot:toolbar>@can('purchases.export')<x-export-menu :url="route('supplier-payments.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
