<x-layouts.portal title="Statements" portal="investor">
    <x-page-header title="Statements" :breadcrumbs="['Overview' => route('investor.dashboard'), 'Statements' => null]" />
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Capital Balance" :value="App\Support\Format::money($balances['capital'])" icon="tag" tone="accent" />
        <x-portal.figure label="Profit Paid" :value="App\Support\Format::money($balances['paid'])" state="paid" />
        <x-portal.figure label="Approved, Not Yet Paid" :value="App\Support\Format::money($balances['profit_due'])" state="approved" />
    </div>
    <x-data-table :url="route('investor.statements.table')" :search="false" :columns="[
        ['label' => 'Date'], ['label' => 'Entry'], ['label' => 'Description'], ['label' => 'Capital', 'class' => 'text-right'], ['label' => 'Profit', 'class' => 'text-right'], ['label' => 'Capital Balance', 'class' => 'text-right'], ['label' => 'Profit Due', 'class' => 'text-right'],
    ]">
        <x-slot:filters>
            <div class="w-60"><select name="flat" class="form-control" data-table-filter @if (count($flats) > 7) data-select data-placeholder="All units" @endif aria-label="Unit"><option value="">All units</option>@foreach ($flats as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
        </x-slot:filters>
        <x-slot:toolbar>@if (App\Modules\Portal\Services\PortalAccess::allows(auth()->user(), 'investor.portal.statements.export'))<x-export-menu :url="route('investor.statements.export')" />@endif</x-slot:toolbar>
    </x-data-table>
</x-layouts.portal>
