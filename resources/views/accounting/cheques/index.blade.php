<x-layouts.app title="Cheques">
    <x-page-header title="Cheques" :breadcrumbs="['Dashboard' => route('dashboard'), 'Accounting' => null, 'Cheques' => null]" />
    <x-data-table summary data-table-history :url="route('accounting.cheques.table')" sort="received" dir="desc" search="Search cheque no, bank or customer" :columns="[
        ['label' => 'Cheque', 'sort' => 'date'], ['label' => 'From'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'], ['label' => '', 'class' => 'w-72'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-44" data-table-filter aria-label="Status"><option value="">Not settled yet</option><option value="all">All cheques</option>@foreach (App\Models\Cheque::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <x-period />
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
