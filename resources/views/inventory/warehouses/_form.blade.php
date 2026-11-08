<form method="POST" action="{{ $warehouse->exists ? route('warehouses.update', $warehouse) : route('warehouses.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($warehouse->exists) @method('PUT') @endif
    <x-form.input name="name" label="Name" :value="$warehouse->name" required autofocus />
    <x-form.input name="code" label="Code" :value="$warehouse->code" placeholder="WH-LV" required />
    <x-form.select name="project" label="Project" :url="route('options.projects')" :options="$warehouse->project ? [$warehouse->project->public_id => $warehouse->project->name] : []" :selected="$warehouse->project?->public_id" placeholder="Company warehouse" wrapper-class="sm:col-span-2" />
    <x-form.input name="address" label="Address" :value="$warehouse->address" wrapper-class="sm:col-span-2" />
    <x-form.checkbox name="is_active" label="Active" :checked="$warehouse->is_active" />
    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save</x-button>
    </x-form.actions>
</form>
