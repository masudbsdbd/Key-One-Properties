<form method="POST" action="{{ $contractor->exists ? route('contractors.update', $contractor) : route('contractors.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($contractor->exists) @method('PUT') @endif
    <x-form.input name="name" label="Name" :value="$contractor->name" required autofocus />
    <x-form.input name="trade" label="Trade" :value="$contractor->trade" placeholder="Civil, Electrical, Plumbing" />
    <x-form.input name="contact_person" label="Contact Person" :value="$contractor->contact_person" />
    <x-form.input name="mobile" label="Mobile" :value="$contractor->mobile" inputmode="tel" />
    <x-form.input name="email" type="email" label="Email" :value="$contractor->email" />
    <x-form.input name="tax_id" label="TIN" :value="$contractor->tax_id" />
    <x-form.input name="address" label="Address" :value="$contractor->address" wrapper-class="sm:col-span-2" />
    <x-form.select name="status" label="Status" :options="App\Models\Contractor::STATUSES" :selected="$contractor->status" required />
    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save</x-button>
    </x-form.actions>
</form>
