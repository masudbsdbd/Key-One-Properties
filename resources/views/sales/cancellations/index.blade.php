<x-layouts.app title="Cancellations">
    <x-page-header title="Cancellations" :breadcrumbs="['Dashboard' => route('dashboard'), 'Sales' => route('sales.overview'), 'Cancellations' => null]" />
    <x-data-table :url="route('cancellations.table')" sort="date" dir="desc" search="Search cancellation or reason" :columns="[
        ['label' => 'Cancellation', 'sort' => 'date'], ['label' => 'Booking / Sale'], ['label' => 'Customer'], ['label' => 'Refund', 'class' => 'text-right'], ['label' => 'Status'], ['label' => '', 'class' => 'w-24'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-40" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\SalesCancellation::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
