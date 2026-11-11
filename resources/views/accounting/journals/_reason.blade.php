<form method="POST" action="{{ route('accounting.journals.reason.store', [$journal, $action]) }}" data-ajax-form class="space-y-5">
    @csrf
    @if ($action === 'reverse')
        <x-form.input name="date" type="date" label="Reversal Date" :value="max($journal->accounting_date->toDateString(), App\Modules\Accounting\Services\Ledger::today()->toDateString())" required />
    @endif
    <x-form.textarea name="reason" label="Reason" rows="3" required />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" :variant="$action === 'reverse' ? 'danger' : 'primary'">{{ $action === 'reverse' ? 'Reverse Journal' : 'Reject Journal' }}</x-button>
    </x-form.actions>
</form>
