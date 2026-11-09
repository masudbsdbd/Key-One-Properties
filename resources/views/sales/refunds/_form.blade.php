<form method="POST" action="{{ route('refunds.store', $sale ? ['sale' => $sale->public_id] : ['booking' => $booking->public_id]) }}" data-ajax-form class="space-y-5">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    <div class="flex items-center justify-between rounded-card bg-surface-muted px-4 py-3 text-sm"><span class="text-ink-500">Refundable</span><x-money :value="$refundable" class="font-semibold text-ink-900" /></div>
    <x-form.input name="amount" label="Amount" inputmode="decimal" required autofocus />
    <x-form.textarea name="reason" label="Reason" rows="2" required />
    <x-form.actions class="mt-0"><x-button variant="secondary" data-modal-close>Cancel</x-button><x-button type="submit" icon="check">Request Refund</x-button></x-form.actions>
</form>
