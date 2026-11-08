<form method="POST" action="{{ $address->exists ? route('customers.addresses.update', [$customer, $address]) : route('customers.addresses.store', $customer) }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($address->exists) @method('PUT') @endif
    <x-form.select name="address_type" label="Type" :options="App\Models\CustomerAddress::TYPES" :selected="$address->address_type" required />
    <x-form.input name="country" label="Country" :value="$address->country" required />
    <x-form.input name="address_line" label="Address" :value="$address->address_line" required autofocus wrapper-class="sm:col-span-2" />
    <x-form.input name="city" label="City / Area" :value="$address->city" />
    <x-form.input name="district" label="District" :value="$address->district" />
    <x-form.input name="postal_code" label="Postal Code" :value="$address->postal_code" />
    <div class="flex items-end pb-2"><x-form.checkbox name="is_primary" label="Primary address" :checked="$address->is_primary" /></div>
    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save</x-button>
    </x-form.actions>
</form>
