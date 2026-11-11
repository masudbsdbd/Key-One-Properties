@php
    $labels = ['in' => ['Deposit Into', 'Money From'], 'out' => ['Withdraw From', 'Money To'], 'transfer' => ['From', 'To']][$direction];
    $purposes = $direction === 'in' ? App\Models\MoneyTransaction::IN_PURPOSES : ($direction === 'out' ? App\Models\MoneyTransaction::OUT_PURPOSES : []);
@endphp
<form method="POST" action="{{ route('accounting.cash-bank.move.store') }}" data-ajax-form data-move-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    <input type="hidden" name="direction" value="{{ $direction }}">

    <x-form.select name="account" id="move-account" :label="$labels[0]" :options="$accounts->mapWithKeys(fn ($a) => [$a->public_id => $a->label()])" :selected="$account?->public_id" required />
    <x-form.select name="other" id="move-other" :label="$labels[1]" required data-move-other>
        <optgroup label="Your accounts">
            @foreach ($accounts as $a)<option value="account:{{ $a->public_id }}">{{ $a->label() }}</option>@endforeach
        </optgroup>
        @if ($purposes)
            <optgroup label="{{ $direction === 'in' ? 'From outside' : 'To outside' }}">
                @foreach ($purposes as $purpose => [$label])
                    @if ($purpose !== 'other' || $canPickAccount)<option value="purpose:{{ $purpose }}">{{ $label }}</option>@endif
                @endforeach
            </optgroup>
        @endif
    </x-form.select>

    <x-form.input name="amount" label="Amount (BDT)" inputmode="decimal" required />
    <x-form.input name="date" type="date" label="Date" :value="App\Modules\Accounting\Services\Ledger::today()->toDateString()" required />
    <div data-move-when="account" hidden><x-form.input name="charge" label="Charge (BDT)" inputmode="decimal" /></div>
    <div data-move-when="purpose" hidden><x-form.input name="party_name" label="Name" /></div>
    @if ($canPickAccount)
        <div data-move-when="other" hidden><x-form.select name="counter" id="move-counter" label="Ledger Account" :url="route('options.ledger-accounts')" placeholder="Select account" /></div>
    @endif
    <x-form.input name="reference" label="Reference" />
    <x-form.input name="description" label="Note" wrapper-class="sm:col-span-2" />

    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ ['in' => 'Deposit', 'out' => 'Withdraw', 'transfer' => 'Transfer'][$direction] }}</x-button>
    </x-form.actions>
</form>
