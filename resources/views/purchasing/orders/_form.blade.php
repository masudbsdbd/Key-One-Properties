@php
    $today = now(config('app.display_timezone'))->toDateString();
    $supplierOptions = $supplier ? [$supplier->public_id => $supplier->name.' · '.$supplier->supplier_code] : [];
    $warehouseOptions = $order->warehouse ? [$order->warehouse->public_id => $order->warehouse->name] : [];
@endphp
<form method="POST" action="{{ $order->exists ? route('purchase-orders.update', $order) : route('purchase-orders.store') }}" data-ajax-form class="space-y-5">
    @csrf
    @if ($order->exists) @method('PUT') @endif
    <div class="grid gap-5 sm:grid-cols-4">
        <x-form.select name="supplier" label="Supplier" :url="route('options.suppliers')" :options="$supplierOptions" :selected="$supplier?->public_id" required placeholder="Search suppliers" wrapper-class="sm:col-span-2" />
        <x-form.select name="warehouse" id="po-warehouse" label="Deliver To" :url="route('options.warehouses')" :options="$warehouseOptions" :selected="$order->warehouse?->public_id" required placeholder="Search warehouses" wrapper-class="sm:col-span-2" />
        <x-form.input name="order_date" type="date" label="Order Date" :value="$order->order_date?->toDateString() ?? $today" required />
        <x-form.input name="expected_date" type="date" label="Expected Delivery" :value="$order->expected_date?->toDateString()" />
    </div>
    <x-line-items pricing cost-label="Unit Price" :lines="$lines" />
    <x-form.textarea name="notes" label="Notes" rows="2" :value="$order->notes" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save Draft</x-button>
    </x-form.actions>
</form>
