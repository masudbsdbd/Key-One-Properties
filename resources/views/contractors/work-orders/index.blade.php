<x-layouts.app title="Work Orders">
    <x-page-header title="Work Orders" :breadcrumbs="['Dashboard' => route('dashboard'), 'Work Orders' => null]">
        @can('contractors.workorder.create')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('work-orders.create') }}" data-size="max-w-3xl" data-auto-create>New Work Order</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table :url="route('work-orders.table')" sort="date" dir="desc" search="Search work order or title" :columns="[
        ['label' => 'Work Order', 'sort' => 'date'], ['label' => 'Contractor'], ['label' => 'Project'], ['label' => 'Contract', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Progress'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\ContractorWorkOrder::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-56"><select name="contractor" class="form-control" data-table-filter data-select data-select-url="{{ route('options.contractors') }}" data-placeholder="All contractors" aria-label="Contractor"><option value=""></option></select></div>
            <div class="w-56"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option></select></div>
        </x-slot:filters>
        <x-slot:toolbar>@can('contractors.export')<x-export-menu :url="route('work-orders.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
