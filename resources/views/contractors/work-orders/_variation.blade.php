<form method="POST" action="{{ route('work-orders.variations.store', $order) }}" data-ajax-form data-reload-on-success class="space-y-5">
    @csrf
    <dl class="grid grid-cols-2 gap-4 rounded-card bg-surface-muted px-4 py-3 text-sm">
        <div><dt class="text-ink-500">Current Contract</dt><dd class="font-semibold text-ink-900"><x-money :value="$order->contract_amount" /></dd></div>
        <div><dt class="text-ink-500">Measured</dt><dd class="font-semibold text-ink-900"><x-money :value="$measured" /></dd></div>
    </dl>
    <x-form.input name="new_amount" label="Revised Contract Amount" inputmode="decimal" required autofocus />
    <x-form.textarea name="reason" label="Reason" rows="2" required />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Request</x-button>
    </x-form.actions>
</form>
