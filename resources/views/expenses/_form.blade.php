@php
    $user = auth()->user();
    $scopes = collect(App\Models\Expense::SCOPES)->filter(fn ($l, $s) => $user->can('expenses.create.'.$s))->all();
    $today = now(config('app.display_timezone'))->toDateString();
    $projectOptions = $expense->project ? [$expense->project->public_id => $expense->project->name] : [];
    $packageOptions = $expense->workPackage ? [$expense->workPackage->public_id => $expense->workPackage->label()] : [];
    $categoryOptions = $expense->category ? [$expense->category->public_id => $expense->category->label().' · '.$expense->category->code] : [];
@endphp
<form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2" data-expense-form>
    @csrf
    @if ($expense->exists) @method('PUT') @endif
    <x-form.select name="scope" id="exp-scope" label="Scope" :options="$scopes" :selected="$expense->scope" required :enhance="false" />
    <x-form.select name="category" label="Category" :url="route('options.expense-categories')" depends-on="#exp-scope" depends-param="scope" :options="$categoryOptions" :selected="$expense->category?->public_id" required placeholder="Search categories" />
    <div class="grid gap-5 sm:col-span-2 sm:grid-cols-2" data-expense-project @if ($expense->scope !== 'project') hidden @endif>
        <x-form.select name="project" id="exp-project" label="Project" :url="route('options.projects')" :options="$projectOptions" :selected="$expense->project?->public_id" required placeholder="Search projects" />
        <x-form.select name="work_package" label="Work Package" :url="route('options.work-packages')" depends-on="#exp-project" depends-param="project" :options="$packageOptions" :selected="$expense->workPackage?->public_id" placeholder="None" />
    </div>
    <x-form.input name="description" label="Description" :value="$expense->description" required wrapper-class="sm:col-span-2" />
    <x-form.input name="payee_name" label="Paid To" :value="$expense->payee_name" />
    <div class="grid grid-cols-2 gap-5">
        <x-form.input name="expense_date" type="date" label="Date" :value="$expense->expense_date?->toDateString() ?? $today" :max="$today" required />
        <x-form.input name="amount" label="Amount" :value="$expense->amount" inputmode="decimal" placeholder="0.00" required />
    </div>
    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        @if ($expense->exists)
            <x-button type="submit" icon="check">Save</x-button>
        @else
            <x-button type="submit" variant="secondary">Save Draft</x-button>
            <x-button type="submit" name="submit" value="1" icon="arrow-right">Submit</x-button>
        @endif
    </x-form.actions>
</form>
