<x-layouts.app title="Journals">
    <x-page-header title="Journals" :breadcrumbs="['Dashboard' => route('dashboard'), 'Accounting' => null, 'Journals' => null]">
        @can('accounting.journal.create')
            <x-slot:actions><x-button icon="plus" :href="route('accounting.journals.create')">New Journal</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table data-table-history :url="route('accounting.journals.table')" sort="date" dir="desc" search="Search journal no, details or reference" :columns="[
        ['label' => 'Journal', 'sort' => 'date'], ['label' => 'Type'], ['label' => 'Details'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="type" class="form-control w-44" data-table-filter aria-label="Type"><option value="">All types</option>@foreach (App\Models\JournalEntry::TYPES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <select name="status" class="form-control w-48" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\JournalEntry::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach<option value="reversed">Reversed</option></select>
            <x-period />
        </x-slot:filters>
        @can('accounting.reports.export')
            <x-slot:toolbar><x-export-menu :url="route('accounting.journals.export')" /></x-slot:toolbar>
        @endcan
    </x-data-table>
</x-layouts.app>
