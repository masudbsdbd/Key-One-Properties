<x-layouts.app title="Dues & Overdue">
    <x-page-header title="Dues & Overdue" :breadcrumbs="['Dashboard' => route('dashboard'), 'Collections' => route('collections.overview'), 'Dues' => null]" />
    <div class="grid gap-6 xl:grid-cols-[320px_1fr]">
        <x-card title="Aging">@include('collections.dues._aging', ['aging' => $aging])</x-card>
        <x-data-table class="min-w-0" :url="route('dues.table')" search="Search customer, sale or unit" :columns="[
            ['label' => 'Customer'], ['label' => 'Sale / Unit'], ['label' => 'Due'], ['label' => 'Principal', 'class' => 'text-right'], ['label' => 'Late Fee', 'class' => 'text-right'], ['label' => 'Outstanding', 'class' => 'text-right'],
        ]">
            <x-slot:filters>
                <select name="filter" class="form-control w-40" data-table-filter aria-label="Show"><option value="">All open dues</option><option value="overdue">Overdue</option><option value="month">Due this month</option></select>
                <div class="w-52"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option></select></div>
            </x-slot:filters>
            <x-slot:toolbar>@can('collections.payment.statement.export')<x-export-menu :url="route('dues.export')" />@endcan</x-slot:toolbar>
        </x-data-table>
    </div>
</x-layouts.app>
