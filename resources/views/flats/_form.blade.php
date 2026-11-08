<form method="POST" action="{{ $flat->exists ? route('flats.update', $flat) : route('flats.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-3">
    @csrf
    @if ($flat->exists)
        @method('PUT')
        <div class="sm:col-span-3 flex flex-wrap gap-x-6 gap-y-1 rounded-card bg-surface-muted px-4 py-3 text-sm">
            <span><span class="text-ink-500">Project:</span> {{ $flat->project->name }}</span>
            <span><span class="text-ink-500">Building:</span> {{ $flat->building->name }}</span>
        </div>
        <input type="hidden" id="flat-building-fixed" value="{{ $flat->building->public_id }}">
        <x-form.select name="floor" label="Floor" :url="route('options.floors')" depends-on="#flat-building-fixed" depends-param="building" :options="[$flat->floor->public_id => $flat->floor->label]" :selected="$flat->floor->public_id" required />
    @else
        <x-form.select name="project" label="Project" :url="route('options.projects')" :options="$project ? [$project->public_id => $project->name.' ('.$project->code.')'] : []" :selected="$project?->public_id" required placeholder="Search projects" />
        <x-form.select name="building" label="Building" :url="route('options.buildings')" depends-on="#f-project" depends-param="project" required placeholder="Select building" />
        <x-form.select name="floor" label="Floor" :url="route('options.floors')" depends-on="#f-building" depends-param="building" required placeholder="Select floor" />
    @endif

    <x-form.input name="flat_no" label="Unit No." :value="$flat->flat_no" placeholder="5A" required autofocus />
    <x-form.select name="flat_type" label="Type" :options="App\Models\Flat::TYPES" :selected="$flat->flat_type" placeholder="Select type" />
    <x-form.select name="facing" label="Facing" :options="App\Models\Flat::FACINGS" :selected="$flat->facing" placeholder="Select facing" />
    <x-form.input name="bedrooms" type="number" label="Bedrooms" :value="$flat->bedrooms" min="0" max="20" />
    <x-form.input name="bathrooms" type="number" label="Bathrooms" :value="$flat->bathrooms" min="0" max="20" />
    <x-form.input name="balconies" type="number" label="Balconies" :value="$flat->balconies" min="0" max="20" />
    <x-form.input name="saleable_area" label="Saleable Area" :value="$flat->saleable_area" inputmode="decimal" required />
    <x-form.select name="area_unit" label="Area Unit" :options="['sqft' => 'sq ft', 'sqm' => 'sq m']" :selected="$flat->area_unit ?? 'sqft'" required />
    @unless ($flat->exists)
        <x-form.input name="list_price" label="List Price (BDT)" inputmode="decimal" />
    @endunless
    <x-form.textarea name="description" label="Description" :value="$flat->description" rows="2" wrapper-class="sm:col-span-3" />

    <x-form.actions class="sm:col-span-3 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $flat->exists ? 'Save' : 'Create Unit' }}</x-button>
    </x-form.actions>
</form>
