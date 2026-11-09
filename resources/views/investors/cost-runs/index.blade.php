<x-layouts.app title="Cost Allocation">
    <x-page-header title="Cost Allocation" :breadcrumbs="['Dashboard' => route('dashboard'), 'Investors' => route('investors.index'), 'Cost Allocation' => null]">
        @can('investor.cost.allocate')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('cost-runs.create') }}" data-size="max-w-lg" data-auto-create>New Run</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table :url="route('cost-runs.table')" sort="date" dir="desc" search="Search run or project" :columns="[
        ['label' => 'Run', 'sort' => 'date'], ['label' => 'Project'], ['label' => 'Cutoff', 'sort' => 'cutoff'], ['label' => 'Saleable Area', 'class' => 'text-right'], ['label' => 'Shared Cost', 'class' => 'text-right'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-40" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\FlatCostAllocationRun::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-52"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option></select></div>
        </x-slot:filters>
        <x-slot:toolbar>@can('investor.ledger.export')<x-export-menu :url="route('cost-runs.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
