<x-layouts.app title="Contractor Bills">
    <x-page-header title="Contractor Bills" :breadcrumbs="['Dashboard' => route('dashboard'), 'Contractor Bills' => null]" />
    <x-data-table :url="route('contractor-bills.table')" sort="date" dir="desc" search="Search bill number" :columns="[
        ['label' => 'Bill', 'sort' => 'date'], ['label' => 'Contractor'], ['label' => 'Work Order'], ['label' => 'Gross', 'class' => 'text-right'], ['label' => 'Net Payable', 'class' => 'text-right', 'sort' => 'net'], ['label' => 'Outstanding', 'class' => 'text-right'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\ContractorBill::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-56"><select name="contractor" class="form-control" data-table-filter data-select data-select-url="{{ route('options.contractors') }}" data-placeholder="All contractors" aria-label="Contractor"><option value=""></option></select></div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
