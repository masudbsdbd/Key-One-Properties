<x-layouts.app title="Contractor Payments">
    <x-page-header title="Contractor Payments" :breadcrumbs="['Dashboard' => route('dashboard'), 'Contractor Payments' => null]">
        @can('contractors.payment.create')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('contractor-payments.create') }}" data-size="max-w-2xl" data-auto-create>New Payment</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table summary data-table-history :url="route('contractor-payments.table')" sort="date" dir="desc" search="Search payment number or reference" :columns="[
        ['label' => 'Payment', 'sort' => 'date'], ['label' => 'Contractor'], ['label' => 'Against'], ['label' => 'Method'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\ContractorPayment::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-56"><select name="contractor" class="form-control" data-table-filter data-select data-select-url="{{ route('options.contractors') }}" data-placeholder="All contractors" aria-label="Contractor"><option value=""></option></select></div>
            <select name="method" class="form-control w-40" data-table-filter aria-label="Method"><option value="">All methods</option>@foreach (App\Models\ContractorPayment::METHODS as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <x-period />
        </x-slot:filters>
        <x-slot:toolbar>@can('contractors.export')<x-export-menu :url="route('contractor-payments.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
