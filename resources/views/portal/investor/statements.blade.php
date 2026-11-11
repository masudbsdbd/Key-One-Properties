<x-layouts.portal title="Statement" portal="investor">
    <x-page-header title="Statement" :breadcrumbs="['Home' => route('investor.dashboard'), 'Statement' => null]" />
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card label="You Have Invested" :value="App\Support\Format::money($balances['capital'])" icon="tag" tone="accent" />
        <x-stat-card label="Profit Received" :value="App\Support\Format::money($balances['paid'])" icon="check" tone="success" />
        <x-stat-card label="Profit to Receive" :value="App\Support\Format::money($balances['profit_due'])" icon="clock" tone="warning" />
    </div>
    <x-data-table :url="route('investor.statements.table')" :search="false" :columns="[
        ['label' => 'Date'], ['label' => 'What Happened'], ['label' => 'Invested', 'class' => 'text-right'], ['label' => 'Profit', 'class' => 'text-right'], ['label' => 'Total Invested', 'class' => 'text-right'], ['label' => 'Profit to Receive', 'class' => 'text-right'],
    ]">
        <x-slot:filters>
            <div class="w-60"><select name="flat" class="form-control" data-table-filter @if (count($flats) > 7) data-select data-placeholder="All units" @endif aria-label="Unit"><option value="">All units</option>@foreach ($flats as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
        </x-slot:filters>
        <x-slot:toolbar>@if (App\Modules\Portal\Services\PortalAccess::allows(auth()->user(), 'investor.portal.statements.export'))<x-export-menu :url="route('investor.statements.export')" />@endif</x-slot:toolbar>
    </x-data-table>
</x-layouts.portal>
