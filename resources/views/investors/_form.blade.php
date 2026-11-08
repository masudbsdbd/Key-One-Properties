<form method="POST" action="{{ $investor->exists ? route('investors.update', $investor) : route('investors.store') }}" data-ajax-form @if ($investor->exists) data-reload-on-success @endif class="space-y-5">
    @csrf
    @if ($investor->exists) @method('PUT') @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <x-form.input name="name" label="Name" :value="$investor->name" required autofocus wrapper-class="sm:col-span-2" />
        <x-form.input name="mobile" label="Mobile" :value="$investor->mobile" inputmode="tel" />
        <x-form.input name="email" type="email" label="Email" :value="$investor->email" />
        <x-form.input name="tax_id" label="TIN" :value="$investor->tax_id" />
        <x-form.select name="status" label="Status" :options="App\Models\Investor::STATUSES" :selected="$investor->status" required :enhance="false" />
        <x-form.input name="address" label="Address" :value="$investor->address" wrapper-class="sm:col-span-2" />
    </div>
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $investor->exists ? 'Save' : 'Add Investor' }}</x-button>
    </x-form.actions>
</form>
