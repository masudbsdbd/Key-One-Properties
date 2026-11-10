@php $destination ??= null; @endphp
<form method="POST" action="{{ route('borrows.store') }}" data-ajax-form class="space-y-5">
    @csrf
    <div class="grid gap-5 sm:grid-cols-2">
        <x-form.select name="source_warehouse" id="borrow-source" label="Borrow From (warehouse)" :url="route('options.warehouses', ['kind' => 'project'])" required placeholder="Search project warehouses" />
        <x-form.select name="destination_warehouse" id="borrow-destination" label="Deliver To (your warehouse)" :url="route('options.warehouses', ['kind' => 'project'])" :options="$destination ? [$destination->public_id => $destination->name.' ('.$destination->code.')'] : []" :selected="$destination?->public_id" required placeholder="Search project warehouses" />
    </div>
    <x-line-items available-url="{{ route('stock.available') }}" warehouse-selector="#borrow-source" />
    <x-form.textarea name="notes" label="Notes" rows="2" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Request</x-button>
    </x-form.actions>
</form>
