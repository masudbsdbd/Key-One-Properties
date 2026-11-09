<x-layouts.app title="Installment Plans">
    <x-page-header title="Installment Plans" :breadcrumbs="['Dashboard' => route('dashboard'), 'Collections' => route('collections.overview'), 'Plans' => null]">
        <x-slot:actions>@can('collections.plan.create')<x-button icon="plus" data-remote-modal="{{ route('installment-plans.create') }}" data-size="max-w-4xl" data-auto-create>New Plan</x-button>@endcan</x-slot:actions>
    </x-page-header>
    <x-data-table :url="route('installment-plans.table')" sort="date" dir="desc" search="Search plan, sale or customer" :columns="[
        ['label' => 'Plan', 'sort' => 'date'], ['label' => 'Sale / Unit'], ['label' => 'Customer'], ['label' => 'Principal', 'class' => 'text-right', 'sort' => 'principal'], ['label' => 'Items', 'class' => 'text-right'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-40" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\InstallmentPlan::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-52"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option></select></div>
        </x-slot:filters>
        <x-slot:toolbar>@can('collections.payment.statement.export')<x-export-menu :url="route('installment-plans.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
