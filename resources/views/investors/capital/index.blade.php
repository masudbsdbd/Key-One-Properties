<x-layouts.app title="Investor Capital">
    <x-page-header title="Investor Capital" :breadcrumbs="['Dashboard' => route('dashboard'), 'Investors' => route('investors.index'), 'Capital' => null]">
        @can('investor.capital.record')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('investor-capital.create') }}" data-size="max-w-2xl" data-auto-create>Receive Capital</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table :url="route('investor-capital.table')" sort="date" dir="desc" search="Search transaction, reference or investor" :columns="[
        ['label' => 'Transaction', 'sort' => 'date'], ['label' => 'Investor'], ['label' => 'Unit'], ['label' => 'Type'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="type" class="form-control w-40" data-table-filter aria-label="Type"><option value="">All types</option>@foreach (App\Models\InvestorCapitalTransaction::TYPES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\InvestorCapitalTransaction::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-52"><select name="investor" class="form-control" data-table-filter data-select data-select-url="{{ route('options.investors') }}" data-placeholder="All investors" aria-label="Investor"><option value=""></option></select></div>
        </x-slot:filters>
        <x-slot:toolbar>@can('investor.ledger.export')<x-export-menu :url="route('investor-capital.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
