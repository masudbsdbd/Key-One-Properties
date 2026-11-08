<form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($supplier->exists) @method('PUT') @endif
    <x-form.input name="name" label="Name" :value="$supplier->name" required autofocus wrapper-class="sm:col-span-2" />
    <x-form.input name="contact_person" label="Contact Person" :value="$supplier->contact_person" />
    <x-form.input name="mobile" label="Mobile" :value="$supplier->mobile" inputmode="tel" />
    <x-form.input name="email" type="email" label="Email" :value="$supplier->email" />
    <x-form.input name="tax_id" label="TIN / BIN" :value="$supplier->tax_id" />
    <x-form.input name="address" label="Address" :value="$supplier->address" wrapper-class="sm:col-span-2" />
    <x-form.select name="status" label="Status" :options="App\Models\Supplier::STATUSES" :selected="$supplier->status" required />
    @unless ($supplier->exists)
        <x-form.input name="opening_balance" label="Opening Payable" value="0" inputmode="decimal" data-decimal required />
    @endunless
    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save</x-button>
    </x-form.actions>
</form>
