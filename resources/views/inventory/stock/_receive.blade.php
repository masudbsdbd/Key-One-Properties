<form method="POST" action="{{ route('stock.receive') }}" data-ajax-form class="space-y-5">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    <div class="grid gap-5 sm:grid-cols-3">
        <x-form.select name="warehouse" id="receive-warehouse" label="Warehouse" :url="route('options.warehouses')" required placeholder="Search warehouses" />
        <x-form.input name="received_at" type="date" label="Date" :value="now(config('app.display_timezone'))->toDateString()" :max="now(config('app.display_timezone'))->toDateString()" required />
        <x-form.select name="source" label="Source" :options="['opening' => 'Opening Stock', 'other' => 'Other Receipt']" selected="opening" required />
        <x-form.input name="reference" label="Reference" wrapper-class="sm:col-span-3" />
    </div>
    <x-line-items cost available-url="{{ route('stock.available') }}" warehouse-selector="#receive-warehouse" />
    <x-form.textarea name="notes" label="Notes" rows="2" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Receive</x-button>
    </x-form.actions>
</form>
