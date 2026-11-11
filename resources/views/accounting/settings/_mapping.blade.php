<form method="POST" action="{{ route('accounting.settings.mapping') }}" data-ajax-form class="space-y-5">
    @csrf
    @method('PUT')
    <input type="hidden" name="key" value="{{ $current->system_key }}">
    <div><p class="form-label">Now</p><p class="detail-value">{{ $current->label() }}</p></div>
    <x-form.select name="account" id="mapping-account" label="Use Instead" :url="route('options.ledger-accounts', ['category' => $current->category])" placeholder="Select account" required />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save</x-button>
    </x-form.actions>
</form>
