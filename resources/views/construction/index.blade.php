<x-layouts.app title="Construction">
    <x-page-header title="Construction" :breadcrumbs="['Dashboard' => route('dashboard'), 'Construction' => null]" />

    <x-data-table :url="route('construction.table')" sort="name" search="Search projects" :columns="[
        ['label' => 'Project', 'sort' => 'name'],
        ['label' => 'Work Packages'],
        ['label' => 'Progress', 'class' => 'w-48'],
        ['label' => 'Pending'],
        ['label' => 'Cost to Date', 'sort' => 'cost', 'class' => 'text-right'],
    ]" />
</x-layouts.app>
