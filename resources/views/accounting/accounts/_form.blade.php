<form method="POST" action="{{ $account->exists ? route('accounting.accounts.update', $account) : route('accounting.accounts.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($account->exists) @method('PUT') @endif

    <x-form.input name="name" label="Account Name" :value="$account->name" required autofocus />
    @if ($account->is_system)
        <div><p class="form-label">Code</p><p class="detail-value">{{ $account->code }}</p><input type="hidden" name="code" value="{{ $account->code }}"></div>
    @else
        <x-form.input name="code" label="Code" :value="$account->code" required />
    @endif
    @if ($account->exists)
        <div><p class="form-label">Type</p><p class="detail-value">{{ App\Modules\Accounting\Support\ChartOfAccounts::CATEGORIES[$account->category] }}</p></div>
        <div class="flex items-end pb-2.5">
            <input type="hidden" name="is_active" value="0">
            <x-form.checkbox name="is_active" label="Active" :checked="$account->is_active" />
        </div>
    @else
        <x-form.select name="category" label="Type" :options="App\Modules\Accounting\Support\ChartOfAccounts::CATEGORIES" :selected="$account->category" required />
        <x-form.select name="parent" label="Under" :options="$groups" placeholder="No group" />
    @endif
    <x-form.input name="description" label="Description" :value="$account->description" wrapper-class="sm:col-span-2" />

    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $account->exists ? 'Save' : 'Add Account' }}</x-button>
    </x-form.actions>
</form>
