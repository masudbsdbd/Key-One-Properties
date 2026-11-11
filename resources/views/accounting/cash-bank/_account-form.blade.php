<form method="POST" action="{{ $account->exists ? route('accounting.cash-bank.update', $account) : route('accounting.cash-bank.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($account->exists) @method('PUT') @endif

    @if ($account->exists)
        <div><p class="form-label">Type</p><p class="detail-value">{{ App\Models\MoneyAccount::KINDS[$account->kind] }}</p></div>
    @else
        <x-form.select name="kind" label="Type" :options="App\Models\MoneyAccount::KINDS" :selected="$account->kind" required />
    @endif
    <x-form.input name="name" label="Name" :value="$account->name" placeholder="Islami Bank, bKash, Office Cash" required autofocus />
    <x-form.input name="account_number" label="Account / Wallet Number" :value="$account->account_number" inputmode="numeric" />
    <x-form.input name="bank_name" label="Bank" :value="$account->bank_name" />
    <x-form.input name="branch" label="Branch" :value="$account->branch" />
    @if ($account->exists)
        <div class="flex items-end pb-2.5">
            <input type="hidden" name="is_active" value="0">
            <x-form.checkbox name="is_active" label="Active" :checked="$account->is_active" />
        </div>
    @else
        <x-form.input name="opening_balance" label="Opening Balance (BDT)" inputmode="decimal" />
        <x-form.input name="opening_date" type="date" label="Balance As Of" :value="App\Modules\Accounting\Services\Ledger::today()->toDateString()" />
    @endif
    <x-form.input name="notes" label="Note" :value="$account->notes" wrapper-class="sm:col-span-2" />

    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $account->exists ? 'Save' : 'Add Account' }}</x-button>
    </x-form.actions>
</form>
