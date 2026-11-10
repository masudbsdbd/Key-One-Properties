<x-data-table summary :url="route('construction.cost', $project)" sort="date" dir="desc" search="Search document number" :columns="[
    ['label' => 'Date', 'sort' => 'date'], ['label' => 'Source'], ['label' => 'Category'], ['label' => 'Work Package'], ['label' => 'Amount', 'sort' => 'amount', 'class' => 'text-right'],
]">
    <x-slot:filters>
        <select name="category" class="form-control w-44" data-table-filter aria-label="Category">
            <option value="">All categories</option>
            @foreach (App\Models\ProjectCostAllocation::CATEGORIES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
        <div class="w-56"><select name="work_package" class="form-control" data-table-filter data-select data-select-url="{{ route('options.work-packages', ['project' => $project->public_id]) }}" data-placeholder="All work packages" aria-label="Work package"><option value=""></option></select></div>
        <x-period />
    </x-slot:filters>
    <x-slot:toolbar>
        @can('construction:cost-export', $project)<x-export-menu :url="route('construction.cost.export', $project)" />@endcan
    </x-slot:toolbar>
</x-data-table>
