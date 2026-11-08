<x-layouts.app title="Customers">
    <x-page-header title="Customers" :breadcrumbs="['Dashboard' => route('dashboard'), 'Customers' => null]">
        @can('create', App\Models\Customer::class)
            <x-slot:actions>
                <x-button icon="user-plus" data-remote-modal="{{ route('customers.create') }}" data-size="max-w-3xl" data-auto-create>New Customer</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-data-table :url="route('customers.table')" sort="created" dir="desc" search="Search name, mobile, email or code" :columns="[
        ['label' => 'Customer', 'sort' => 'name'],
        ['label' => 'Mobile'],
        ['label' => 'Email'],
        ['label' => 'Source'],
        ['label' => 'Status'],
        ['label' => 'Created', 'sort' => 'created'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status">
                <option value="">All statuses</option>
                @foreach (App\Models\Customer::STATUSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
            <div class="w-40">
                <select name="source" class="form-control" data-table-filter data-select data-placeholder="All sources" aria-label="Source">
                    <option value=""></option>
                    @foreach (App\Models\Customer::SOURCES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            @can('customers.view_all')
                @can('users.view')
                    <div class="w-52">
                        <select name="owner" data-table-filter data-select data-select-url="{{ route('options.users') }}" data-placeholder="Any creator" class="form-control" aria-label="Created by"><option value=""></option></select>
                    </div>
                @endcan
            @endcan
            @can('customers.archive')
                <select name="archived" class="form-control w-36" data-table-filter aria-label="Archived">
                    <option value="">Current</option>
                    <option value="1">Archived</option>
                </select>
            @endcan
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
