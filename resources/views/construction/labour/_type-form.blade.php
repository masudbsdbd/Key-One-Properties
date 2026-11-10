<form method="POST" action="{{ route('labour.types.update', $type) }}" data-ajax-form data-refresh-partials class="space-y-5">
    @csrf
    @method('PUT')
    <x-form.input name="name" label="Name" :value="$type->name" maxlength="60" required />
    <x-form.input name="default_rate" label="Default Daily Rate (BDT)" :value="$type->default_rate !== null ? rtrim(rtrim($type->default_rate, '0'), '.') : ''" inputmode="decimal" />
    <x-form.checkbox name="is_active" label="Active" :checked="$type->is_active" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save</x-button>
    </x-form.actions>
</form>
