<form method="POST" action="{{ route('accounting.cheques.update', [$cheque, $action]) }}" data-ajax-form class="space-y-5">
    @csrf
    <div class="rounded-card border border-line bg-surface-muted px-4 py-3 text-sm">
        <p class="font-semibold text-ink-900">{{ $cheque->customer?->full_name }} · <x-money :value="$cheque->amount" /></p>
        <p class="text-ink-500">Cheque {{ $cheque->cheque_no }}@if ($cheque->bank_name) · {{ $cheque->bank_name }}@endif</p>
    </div>
    @if ($action !== 'bounce')
        <x-form.select name="money_account" id="cheque-account" label="Bank Account" :options="$accounts" :selected="$cheque->moneyAccount?->public_id" required />
    @endif
    <x-form.input name="date" type="date" label="Date" :value="App\Modules\Accounting\Services\Ledger::today()->toDateString()" required />
    @if ($action === 'bounce')
        <x-form.input name="note" label="Reason" />
    @endif
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" :variant="$action === 'bounce' ? 'danger' : 'primary'" icon="check">{{ ['deposit' => 'Deposit', 'clear' => 'Mark Cleared', 'bounce' => 'Mark Bounced'][$action] }}</x-button>
    </x-form.actions>
</form>
