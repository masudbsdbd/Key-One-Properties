<x-layouts.app title="Construction">
    <x-page-header title="Construction" :breadcrumbs="['Dashboard' => route('dashboard'), 'Construction' => null]" />

    <x-data-table summary data-table-history :url="route('construction.table')" sort="name" search="Search projects" :columns="[
        ['label' => 'Project', 'sort' => 'name'],
        ['label' => 'Work Packages'],
        ['label' => 'Progress', 'class' => 'w-48'],
        ['label' => 'Pending'],
        ['label' => 'Cost to Date', 'sort' => 'cost', 'class' => 'text-right'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-40" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\Project::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
        </x-slot:filters>
        <x-slot:toolbar><x-export-menu :url="route('construction.export')" /></x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
