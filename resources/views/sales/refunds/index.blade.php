<x-layouts.app title="Refunds">
    <x-page-header title="Customer Refunds" :breadcrumbs="['Dashboard' => route('dashboard'), 'Sales' => route('sales.overview'), 'Refunds' => null]" />
    <x-data-table :url="route('refunds.table')" sort="date" dir="desc" search="Search refund, reason or customer" :columns="[
        ['label' => 'Refund', 'sort' => 'date'], ['label' => 'Customer'], ['label' => 'Against'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-40" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\CustomerRefund::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-56"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option></select></div>
        </x-slot:filters>
        <x-slot:toolbar>@can('sales.export')<x-export-menu :url="route('refunds.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
