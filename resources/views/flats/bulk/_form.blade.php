<form method="POST" action="{{ route('projects.flats.bulk.store', $project) }}" data-ajax-form data-bulk-form data-preview-url="{{ route('projects.flats.bulk.preview', $project) }}" class="space-y-6">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

    @if ($buildings->isEmpty())
        <x-empty-state icon="building" title="Add a building first" />
    @else
        <div class="grid gap-5 md:grid-cols-3">
            <x-form.select name="building" id="bulk-building" label="Building" :options="$buildings" required />
            <div class="md:col-span-2">
                <x-form.select name="floors" id="bulk-floors" label="Floors" :url="route('options.floors')" depends-on="#bulk-building" depends-param="building" multiple required placeholder="Select floors" />
                <button type="button" class="mt-1.5 text-[13px] font-medium text-accent-700 hover:underline" data-select-all-floors data-url="{{ route('options.floors') }}">Select all floors</button>
            </div>
            <x-form.input name="per_floor" type="number" label="Units per Floor" min="1" max="50" placeholder="4" required />
            <x-form.input name="pattern" label="Number Pattern" value="{floor}{L}" required hint="{floor}, {n} or {L} (A, B, C)" />
            <x-form.input name="start_index" type="number" label="Start From" min="1" value="1" />
            <x-form.select name="flat_type" id="bulk-type" label="Type" :options="App\Models\Flat::TYPES" placeholder="Select type" />
            <x-form.input name="bedrooms" id="bulk-bedrooms" type="number" label="Bedrooms" min="0" max="20" />
            <x-form.input name="bathrooms" id="bulk-bathrooms" type="number" label="Bathrooms" min="0" max="20" />
            <x-form.input name="saleable_area" id="bulk-area" label="Saleable Area" inputmode="decimal" required />
            <x-form.select name="area_unit" id="bulk-unit" label="Area Unit" :options="['sqft' => 'sq ft', 'sqm' => 'sq m']" selected="sqft" required />
            <x-form.input name="list_price" id="bulk-price" label="List Price (BDT)" inputmode="decimal" />
        </div>

        <div data-bulk-preview></div>

        <x-form.actions class="mt-0">
            <x-button variant="secondary" data-modal-close>Cancel</x-button>
            <x-button variant="secondary" icon="eye" data-bulk-preview-button>Preview</x-button>
            <x-button type="submit" icon="check" disabled data-bulk-submit data-disabled-reason="Preview the units first">Create Units</x-button>
        </x-form.actions>
    @endif
</form>
