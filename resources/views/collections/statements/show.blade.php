<x-layouts.app :title="'Statement · '.$customer->full_name">
    <x-page-header :title="$customer->full_name" :breadcrumbs="['Dashboard' => route('dashboard'), 'Customers' => route('customers.index'), $customer->customer_code => route('customers.show', $customer), 'Statement' => null]" />
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Balance" :value="App\Support\Format::money($balance)" icon="tag" :tone="App\Support\Money::isPositive($balance) ? 'warning' : 'success'" />
    </div>
    <x-data-table :url="route('customers.statement.table', $customer)" :search="false" :columns="[
        ['label' => 'Date'], ['label' => 'Type'], ['label' => 'Description'], ['label' => 'Charge', 'class' => 'text-right'], ['label' => 'Credit', 'class' => 'text-right'], ['label' => 'Balance', 'class' => 'text-right'],
    ]">
        <x-slot:toolbar>@can('collections.payment.statement.export')<x-export-menu :url="route('customers.statement.export', $customer)" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
