<form method="POST" action="{{ $building->exists ? route('projects.buildings.update', [$project, $building]) : route('projects.buildings.store', $project) }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($building->exists) @method('PUT') @endif
    <x-form.input name="name" label="Building Name" :value="$building->name" required autofocus />
    <x-form.input name="code" label="Code" :value="$building->code" placeholder="A" required />
    <x-form.select name="status" label="Status" :options="App\Models\Building::STATUSES" :selected="$building->status" required />
    <div></div>
    @unless ($building->exists)
        <x-form.input name="floor_from" type="number" label="Lowest Floor" min="-10" max="200" hint="-1 basement, 0 ground" />
        <x-form.input name="floor_to" type="number" label="Highest Floor" min="-10" max="200" />
    @endunless
    <x-form.textarea name="description" label="Description" :value="$building->description" rows="2" wrapper-class="sm:col-span-2" />
    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $building->exists ? 'Save' : 'Create Building' }}</x-button>
    </x-form.actions>
</form>
