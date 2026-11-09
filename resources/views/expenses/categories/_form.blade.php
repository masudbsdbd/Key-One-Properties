<form method="POST" action="{{ $category->exists ? route('expense-categories.update', $category) : route('expense-categories.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($category->exists) @method('PUT') @endif
    <x-form.input name="code" label="Code" :value="$category->code" required autofocus placeholder="SITE-UTIL" />
    <x-form.input name="name" label="Name" :value="$category->name" required />
    <x-form.select name="parent" label="Parent" :url="route('options.expense-categories')" :options="$category->parent ? [$category->parent->public_id => $category->parent->name] : []" :selected="$category->parent?->public_id" placeholder="None" />
    <x-form.select name="allowed_scope" label="Use For" :options="App\Models\ExpenseCategory::SCOPES" :selected="$category->allowed_scope ?? 'both'" required />
    <x-form.checkbox name="is_active" label="Active" :checked="$category->exists ? $category->is_active : true" />
    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save</x-button>
    </x-form.actions>
</form>
