<form method="POST" action="{{ $material->exists ? route('materials.update', $material) : route('materials.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($material->exists) @method('PUT') @endif
    <x-form.input name="name" label="Name" :value="$material->name" required autofocus />
    <x-form.input name="sku" label="SKU" :value="$material->sku" placeholder="CEM-50KG" required />
    <x-form.select name="category" label="Category" :options="$categories" :selected="$material->category?->public_id" required />
    <x-form.select name="unit" label="Unit" :options="$units" :selected="$material->unit?->public_id" required />
    <x-form.input name="reorder_level" label="Reorder Level" :value="$material->reorder_level" inputmode="decimal" required />
    <div class="flex items-end pb-2"><x-form.checkbox name="is_active" label="Active" :checked="$material->is_active" /></div>
    <x-form.textarea name="description" label="Description" :value="$material->description" rows="2" wrapper-class="sm:col-span-2" />
    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save</x-button>
    </x-form.actions>
</form>
