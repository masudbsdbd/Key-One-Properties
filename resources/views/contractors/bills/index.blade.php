<x-layouts.app title="Contractor Bills">
    <x-page-header title="Contractor Bills" :breadcrumbs="['Dashboard' => route('dashboard'), 'Contractor Bills' => null]" />
    <x-data-table summary data-table-history :url="route('contractor-bills.table')" sort="date" dir="desc" search="Search bill number" :columns="[
        ['label' => 'Bill', 'sort' => 'date'], ['label' => 'Contractor'], ['label' => 'Work Order'], ['label' => 'Gross', 'class' => 'text-right'], ['label' => 'Net Payable', 'class' => 'text-right', 'sort' => 'net'], ['label' => 'Outstanding', 'class' => 'text-right'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\ContractorBill::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-56"><select name="contractor" class="form-control" data-table-filter data-select data-select-url="{{ route('options.contractors') }}" data-placeholder="All contractors" aria-label="Contractor"><option value=""></option></select></div>
            <div class="w-52"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option></select></div>
            <x-period />
        </x-slot:filters>
        <x-slot:toolbar>@can('contractors.export')<x-export-menu :url="route('contractor-bills.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
