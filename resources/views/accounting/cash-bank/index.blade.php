@php $few = $accounts->where('is_active', true)->count() < 2; @endphp
<x-layouts.app title="Cash & Bank">
    <x-page-header title="Cash & Bank" :breadcrumbs="['Dashboard' => route('dashboard'), 'Accounting' => null, 'Cash & Bank' => null]">
        @can('accounting.cashbank.manage')
            <x-slot:actions>
                <x-button variant="secondary" icon="plus" data-remote-modal="{{ route('accounting.cash-bank.create') }}" data-size="max-w-2xl">New Account</x-button>
                <x-button icon="repeat" data-remote-modal="{{ route('accounting.cash-bank.move', ['direction' => 'transfer']) }}" data-size="max-w-2xl" :disabled="$few" :data-disabled-reason="$few ? 'Add two accounts first' : null">Transfer</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div data-partial-url="{{ route('accounting.cash-bank.accounts') }}" data-lazy>
        <div class="grid gap-4 md:grid-cols-4">@for ($i = 0; $i < 4; $i++)<div class="skeleton h-24 w-full"></div>@endfor</div>
    </div>

    <h2 class="mb-3 mt-8 text-[15px] font-semibold text-ink-900">Money In and Out</h2>
    <x-data-table data-table-history :url="route('accounting.cash-bank.table')" search="Search details, journal no or reference" :columns="[
        ['label' => 'Date'], ['label' => 'Account'], ['label' => 'Details'], ['label' => 'In', 'class' => 'text-right'], ['label' => 'Out', 'class' => 'text-right'], ['label' => '', 'class' => 'w-24'],
    ]">
        <x-slot:filters>
            <select name="account" class="form-control w-56" data-table-filter aria-label="Account"><option value="">All accounts</option>@foreach ($accounts as $a)<option value="{{ $a->public_id }}">{{ $a->label() }}</option>@endforeach</select>
            <select name="direction" class="form-control w-36" data-table-filter aria-label="Direction"><option value="">In and out</option><option value="in">Money in</option><option value="out">Money out</option></select>
            <x-period />
        </x-slot:filters>
        @can('accounting.reports.export')
            <x-slot:toolbar><x-export-menu :url="route('accounting.cash-bank.export')" /></x-slot:toolbar>
        @endcan
    </x-data-table>
</x-layouts.app>
