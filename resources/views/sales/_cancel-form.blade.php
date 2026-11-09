<form method="POST" action="{{ $action }}" data-ajax-form data-reload-on-success class="space-y-5">
    @csrf
    <div class="flex items-center justify-between rounded-card bg-surface-muted px-4 py-3 text-sm"><span class="text-ink-500">Received so far</span><x-money :value="$refundable" class="font-semibold text-ink-900" /></div>
    <x-form.textarea name="reason" label="Reason" rows="2" required autofocus />
    <x-form.input name="refund_expected" label="Refund to Customer" value="0" inputmode="decimal" :hint="'Up to '.App\Support\Format::money($refundable)" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Close</x-button>
        <x-button type="submit" variant="danger">Request Cancellation</x-button>
    </x-form.actions>
</form>
