<x-layouts.app title="Expense Categories">
    <x-page-header title="Expense Categories" :breadcrumbs="['Dashboard' => route('dashboard'), 'Expenses' => route('expenses.index'), 'Categories' => null]">
        <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('expense-categories.create') }}" data-size="max-w-xl" data-auto-create>New Category</x-button></x-slot:actions>
    </x-page-header>
    <x-data-table :url="route('expense-categories.table')" sort="code" search="Search code or name" :columns="[
        ['label' => 'Code', 'sort' => 'code'], ['label' => 'Name', 'sort' => 'name'], ['label' => 'Use For'], ['label' => 'Expenses', 'class' => 'text-right'], ['label' => 'Status'], ['label' => '', 'class' => 'w-16'],
    ]" />
</x-layouts.app>
