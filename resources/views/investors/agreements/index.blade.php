<x-layouts.app title="Investment Agreements">
    <x-page-header title="Investment Agreements" :breadcrumbs="['Dashboard' => route('dashboard'), 'Investors' => route('investors.index'), 'Agreements' => null]">
        @can('investor.agreement.create')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('investor-agreements.create') }}" data-size="max-w-3xl" data-auto-create>New Agreement</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table summary data-table-history :url="route('investor-agreements.table')" sort="from" dir="desc" search="Search agreement, investor or unit" :columns="[
        ['label' => 'Agreement', 'sort' => 'from'], ['label' => 'Investor'], ['label' => 'Unit'], ['label' => 'Share', 'class' => 'text-right', 'sort' => 'share'], ['label' => 'Committed', 'class' => 'text-right', 'sort' => 'capital'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-44" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\InvestorFlatAgreement::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-52"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option></select></div>
            <div class="w-52"><select name="investor" class="form-control" data-table-filter data-select data-select-url="{{ route('options.investors') }}" data-placeholder="All investors" aria-label="Investor"><option value=""></option></select></div>
        </x-slot:filters>
        <x-slot:toolbar>@can('investor.ledger.export')<x-export-menu :url="route('investor-agreements.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
