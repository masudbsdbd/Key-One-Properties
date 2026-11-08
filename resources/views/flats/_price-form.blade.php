<form method="POST" action="{{ route('flats.price.update', $flat) }}" data-ajax-form class="space-y-5">
    @csrf
    <div class="flex justify-between rounded-card bg-surface-muted px-4 py-3 text-sm">
        <span class="text-ink-500">Current price</span>
        <span class="font-semibold tabular-nums text-ink-900">{{ App\Support\Format::money($flat->list_price) }}</span>
    </div>
    <x-form.input name="list_price" label="New Price (BDT)" inputmode="decimal" required autofocus />
    <x-form.input name="reason" label="Reason" required />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Update Price</x-button>
    </x-form.actions>
</form>
