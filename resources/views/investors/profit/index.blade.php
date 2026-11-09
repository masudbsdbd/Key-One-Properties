<x-layouts.app title="Flat Profit">
    <x-page-header title="Flat Profit" :breadcrumbs="['Dashboard' => route('dashboard'), 'Investors' => route('investors.index'), 'Flat Profit' => null]">
        @can('investor.profit.calculate')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('profit-calculations.create') }}" data-size="max-w-lg" data-auto-create>New Calculation</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    @if ($blocker)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-card border border-warning-600/30 bg-warning-50 px-5 py-3 text-sm text-warning-600">
            <span>Profit approval is blocked · {{ $blocker }}</span>
            @can('settings.manage')<a href="{{ route('settings.edit') }}" class="font-medium underline">Settings</a>@endcan
        </div>
    @endif
    <x-data-table summary data-table-history :url="route('profit-calculations.table')" sort="date" dir="desc" search="Search calculation or unit" :columns="[
        ['label' => 'Calculation', 'sort' => 'date'], ['label' => 'Unit'], ['label' => 'Revenue', 'class' => 'text-right'], ['label' => 'Cost', 'class' => 'text-right'], ['label' => 'Net Profit', 'class' => 'text-right', 'sort' => 'net'], ['label' => 'Investors', 'class' => 'text-right'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-40" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\FlatProfitCalculation::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-52"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option></select></div>
            <x-period />
        </x-slot:filters>
        <x-slot:toolbar>@can('investor.ledger.export')<x-export-menu :url="route('profit-calculations.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
