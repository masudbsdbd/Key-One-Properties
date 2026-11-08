<form method="POST" action="{{ route('adjustments.store') }}" data-ajax-form class="space-y-5">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-form.select name="warehouse" id="adjust-warehouse" label="Warehouse" :url="route('options.warehouses')" required placeholder="Search warehouses" />
        <x-form.input name="reason" label="Reason" required />
    </div>
    <x-line-items cost signed qty-label="Change (+/−)" cost-label="Unit Cost (for +)" available-url="{{ route('stock.available') }}" warehouse-selector="#adjust-warehouse" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Submit</x-button>
    </x-form.actions>
</form>
