<x-layouts.app title="Suppliers">
    <x-page-header title="Suppliers" :breadcrumbs="['Dashboard' => route('dashboard'), 'Suppliers' => null]">
        @can('suppliers.create')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('suppliers.create') }}" data-size="max-w-2xl" data-auto-create>New Supplier</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table :url="route('suppliers.table')" sort="name" search="Search name, code, contact or mobile" :columns="[
        ['label' => 'Supplier', 'sort' => 'name'], ['label' => 'Contact'], ['label' => 'Payable', 'class' => 'text-right'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\Supplier::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
