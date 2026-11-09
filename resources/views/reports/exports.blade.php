<x-layouts.app title="My Exports">
    <x-page-header title="My Exports" :breadcrumbs="['Dashboard' => route('dashboard'), 'Reports' => route('reports.index'), 'My Exports' => null]" />
    <x-data-table :url="route('report-exports.table')" :search="false" data-exports-poll :columns="[
        ['label' => 'Report'], ['label' => 'Format'], ['label' => 'Status'], ['label' => 'Rows', 'class' => 'text-right'], ['label' => 'Size', 'class' => 'text-right'], ['label' => 'Requested'], ['label' => 'Expires'], ['label' => '', 'class' => 'w-12'],
    ]" />
</x-layouts.app>
