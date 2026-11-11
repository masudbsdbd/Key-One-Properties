<x-layouts.portal title="My Investments" portal="investor">
    <x-page-header title="My Investments" :breadcrumbs="['Home' => route('investor.dashboard'), 'My Investments' => null]" />
    <x-data-table :url="route('investor.investments.table')" sort="from" dir="desc" search="Search unit or agreement" :columns="[
        ['label' => 'Unit', 'sort' => 'from'], ['label' => 'Your Share of Profit', 'class' => 'text-right', 'sort' => 'share'], ['label' => 'Agreed Investment', 'class' => 'text-right', 'sort' => 'capital'], ['label' => 'Since'], ['label' => 'Agreement'], ['label' => ''],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option><option value="active">Active</option><option value="closed">Closed</option></select>
            <div class="w-56"><select name="project" class="form-control" data-table-filter @if (count($projects) > 7) data-select data-placeholder="All projects" @endif aria-label="Project"><option value="">All projects</option>@foreach ($projects as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.portal>
