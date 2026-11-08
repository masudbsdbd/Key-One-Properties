<x-layouts.app title="Price Revisions">
    <x-page-header title="Price Revisions" :breadcrumbs="['Dashboard' => route('dashboard'), 'Sales' => route('sales.overview'), 'Price Revisions' => null]" />
    <x-data-table :url="route('price-revisions.table')" sort="date" dir="desc" search="Search unit or reason" :columns="[
        ['label' => 'Unit', 'sort' => 'date'], ['label' => 'From', 'class' => 'text-right'], ['label' => 'To', 'class' => 'text-right'], ['label' => 'Reason'], ['label' => 'Status'], ['label' => '', 'class' => 'w-24'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-40" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\FlatPriceRevision::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-56"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option></select></div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
