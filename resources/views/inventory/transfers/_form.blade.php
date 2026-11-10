<form method="POST" action="{{ route('transfers.store') }}" data-ajax-form data-transfer-form data-preview-url="{{ route('transfers.preview') }}" class="space-y-5">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    <ol class="flex flex-wrap gap-2 text-[13px] font-medium text-ink-500" aria-label="Steps">
        <li class="badge badge-accent">1 · Warehouses</li><li class="badge badge-neutral">2 · Materials</li><li class="badge badge-neutral">3 · Preview</li><li class="badge badge-neutral">4 · Request</li>
    </ol>
    <div class="grid gap-5 sm:grid-cols-2">
        <x-form.select name="source_warehouse" id="transfer-source" label="From" :url="route('options.warehouses')" :options="$source ? [$source->public_id => $source->name.' ('.$source->code.')'] : []" :selected="$source?->public_id" required placeholder="Search warehouses" />
        <x-form.select name="destination_warehouse" id="transfer-destination" label="To" :url="route('options.warehouses')" :options="$destination ? [$destination->public_id => $destination->name.' ('.$destination->code.')'] : []" :selected="$destination?->public_id" required placeholder="Search warehouses" />
    </div>
    <x-line-items available-url="{{ route('stock.available') }}" warehouse-selector="#transfer-source" :lines="$lines ?? []" />
    <x-form.input name="reason" label="Reason" />
    <div data-transfer-preview></div>
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button variant="secondary" icon="eye" data-transfer-preview-button>Preview</x-button>
        <x-button type="submit" icon="check" disabled data-transfer-submit data-disabled-reason="Preview the transfer first">Request Transfer</x-button>
    </x-form.actions>
</form>
