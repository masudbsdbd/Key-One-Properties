<form method="POST" action="{{ route('distributions.store') }}" data-ajax-form class="space-y-5">
    @csrf
    <input type="hidden" name="investor" value="{{ $investor->public_id }}">
    <input type="hidden" name="flat" value="{{ $flat->public_id }}">
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    <div class="rounded-card border border-line bg-surface-muted px-4 py-3">
        <p class="font-medium text-ink-900">{{ $investor->name }} · {{ $flat->project->code }} Unit {{ $flat->flat_no }}</p>
        <dl class="mt-2 grid grid-cols-3 gap-3 text-sm">
            <div><dt class="text-ink-500">Payable</dt><dd><x-money :value="$balances['payable']" /></dd></div>
            <div><dt class="text-ink-500">Paid</dt><dd><x-money :value="$balances['paid']" /></dd></div>
            <div><dt class="text-ink-500">Available</dt><dd class="font-semibold"><x-money :value="$available" /></dd></div>
        </dl>
    </div>
    <x-form.input name="amount" label="Amount" :value="$available" inputmode="decimal" required autofocus />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Request</x-button>
    </x-form.actions>
</form>
