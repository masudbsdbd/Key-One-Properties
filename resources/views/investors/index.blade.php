<x-layouts.app title="Investors">
    <x-page-header title="Investors" :breadcrumbs="['Dashboard' => route('dashboard'), 'Investors' => null]">
        @can('investor.create')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('investors.create') }}" data-size="max-w-2xl" data-auto-create>New Investor</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table :url="route('investors.table')" sort="name" dir="asc" search="Search name, code or mobile" :columns="[
        ['label' => 'Investor', 'sort' => 'name'], ['label' => 'Contact'], ['label' => 'Active Units', 'class' => 'text-right'], ['label' => 'Capital', 'class' => 'text-right'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-40" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\Investor::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
        </x-slot:filters>
        <x-slot:toolbar>@can('investor.ledger.export')<x-export-menu :url="route('investors.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
