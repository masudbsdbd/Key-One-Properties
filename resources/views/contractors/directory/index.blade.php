<x-layouts.app title="Contractors">
    <x-page-header title="Contractors" :breadcrumbs="['Dashboard' => route('dashboard'), 'Contractors' => null]">
        @can('contractors.create')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('contractors.create') }}" data-size="max-w-2xl" data-auto-create>New Contractor</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table summary data-table-history :url="route('contractors.table')" sort="name" search="Search name, code, trade or mobile" :columns="[
        ['label' => 'Contractor', 'sort' => 'name'], ['label' => 'Trade'], ['label' => 'Contact'], ['label' => 'Active Orders', 'class' => 'text-right'], ['label' => 'Payable', 'class' => 'text-right'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\Contractor::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
        </x-slot:filters>
        <x-slot:toolbar>@can('contractors.export')<x-export-menu :url="route('contractors.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
