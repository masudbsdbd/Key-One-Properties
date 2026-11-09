<x-layouts.portal title="Statement" portal="customer">
    <x-page-header title="Statement" :breadcrumbs="['Overview' => route('customer.dashboard'), 'Statement' => null]" />
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="App\Support\Money::cmp($balance, '0') < 0 ? 'Advance' : 'Balance'" :value="App\Support\Format::money(ltrim($balance, '-'))" icon="tag" tone="accent" />
    </div>
    <x-data-table :url="route('customer.statements.table')" :search="false" :columns="[
        ['label' => 'Date'], ['label' => 'Type'], ['label' => 'Description'], ['label' => 'Charge', 'class' => 'text-right'], ['label' => 'Credit', 'class' => 'text-right'], ['label' => 'Balance', 'class' => 'text-right'],
    ]">
        <x-slot:toolbar><x-export-menu :url="route('customer.statements.export')" /></x-slot:toolbar>
    </x-data-table>
</x-layouts.portal>
