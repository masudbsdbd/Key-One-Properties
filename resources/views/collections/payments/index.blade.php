<x-layouts.app title="Customer Payments">
    <x-page-header title="Customer Payments" :breadcrumbs="['Dashboard' => route('dashboard'), 'Collections' => route('collections.overview'), 'Payments' => null]" />
    <x-data-table :url="route('customer-payments.table')" sort="date" dir="desc" search="Search payment, reference or customer" :columns="[
        ['label' => 'Payment', 'sort' => 'date'], ['label' => 'Customer'], ['label' => 'For'], ['label' => 'Method'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\CustomerPayment::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-52"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option></select></div>
        </x-slot:filters>
        <x-slot:toolbar>@can('collections.payment.statement.export')<x-export-menu :url="route('customer-payments.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
