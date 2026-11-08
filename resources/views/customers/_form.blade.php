@php($canSensitive = ! $customer->exists || auth()->user()->can('viewSensitive', $customer))
<form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($customer->exists) @method('PUT') @endif

    <x-form.input name="full_name" label="Full Name" :value="$customer->full_name" required autofocus wrapper-class="sm:col-span-2" />
    <x-form.input name="primary_mobile" label="Mobile" :value="$customer->primary_mobile" icon="phone" placeholder="01XXXXXXXXX" required />
    <x-form.input name="alternate_mobile" label="Alternate Mobile" :value="$customer->alternate_mobile" icon="phone" />
    <x-form.input name="email" type="email" label="Email" :value="$customer->email" icon="mail" />
    <x-form.input name="occupation" label="Occupation" :value="$customer->occupation" />
    <x-form.input name="date_of_birth" type="date" label="Date of Birth" :value="$customer->date_of_birth?->toDateString()" />
    @if ($canSensitive)
        <x-form.input name="nid_or_passport" label="NID / Passport No." :value="$customer->exists ? $customer->nid_or_passport : null" icon="id-card" autocomplete="off" />
    @endif
    <x-form.select name="source" label="Source" :options="App\Models\Customer::SOURCES" :selected="$customer->source" placeholder="Select source" />
    <x-form.select name="status" label="Status" :options="App\Models\Customer::STATUSES" :selected="$customer->status" required />

    @unless ($customer->exists)
        <div class="sm:col-span-2 border-t border-line pt-5">
            <p class="text-sm font-semibold text-ink-900">Address</p>
        </div>
        <x-form.input name="address_line" label="Address" wrapper-class="sm:col-span-2" />
        <x-form.input name="city" label="City / Area" />
        <x-form.input name="district" label="District" />
    @endunless

    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $customer->exists ? 'Save' : 'Create Customer' }}</x-button>
    </x-form.actions>
</form>
