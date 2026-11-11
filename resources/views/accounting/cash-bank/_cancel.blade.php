<form method="POST" action="{{ route('accounting.cash-bank.cancel.store', $txn) }}" data-ajax-form class="space-y-5">
    @csrf
    <div class="rounded-card border border-line bg-surface-muted px-4 py-3 text-sm">
        <p class="font-medium text-ink-900">{{ $txn->purposeLabel() }} · <x-money :value="$txn->amount" /></p>
        <p class="text-[13px] text-ink-500">{{ App\Support\Format::date($txn->txn_date) }} · {{ $txn->from?->name }}@if ($txn->from && $txn->to) → @endif{{ $txn->to?->name }}</p>
    </div>
    <x-form.textarea name="reason" label="Reason" rows="3" required />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Keep</x-button>
        <x-button type="submit" variant="danger">Cancel Transaction</x-button>
    </x-form.actions>
</form>
