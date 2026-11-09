<x-layouts.app title="Expenses">
    <x-page-header title="Expenses" :breadcrumbs="['Dashboard' => route('dashboard'), 'Expenses' => null]">
        <x-slot:actions>
            @canany(['expenses.create.general', 'expenses.create.project'])<x-button icon="plus" data-remote-modal="{{ route('expenses.create') }}" data-size="max-w-3xl" data-auto-create>New Expense</x-button>@endcanany
            @can('expenses.categories')<x-button variant="secondary" icon="list" href="{{ route('expense-categories.index') }}">Categories</x-button>@endcan
        </x-slot:actions>
    </x-page-header>
    <x-data-table :url="route('expenses.table')" sort="date" dir="desc" search="Search number, description or payee" :columns="[
        ['label' => 'Expense', 'sort' => 'date'], ['label' => 'Category'], ['label' => 'Scope'], ['label' => 'Payee'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\Expense::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <select name="scope" class="form-control w-36" data-table-filter aria-label="Scope"><option value="">All scopes</option>@foreach (App\Models\Expense::SCOPES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-52"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option></select></div>
            <div class="w-52"><select name="category" class="form-control" data-table-filter data-select data-select-url="{{ route('options.expense-categories') }}" data-placeholder="All categories" aria-label="Category"><option value=""></option></select></div>
            <input type="date" name="from" class="form-control w-40" data-table-filter aria-label="From date" data-tooltip="From">
            <input type="date" name="to" class="form-control w-40" data-table-filter aria-label="To date" data-tooltip="To">
        </x-slot:filters>
        @can('expenses.export')
            <x-slot:toolbar><x-export-menu :url="route('expenses.export')" /></x-slot:toolbar>
        @endcan
    </x-data-table>
</x-layouts.app>
