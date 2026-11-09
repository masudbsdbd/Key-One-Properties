@php $today = now(config('app.display_timezone'))->toDateString(); @endphp
<form method="POST" action="{{ route('profit-calculations.store') }}" data-ajax-form class="space-y-5">
    @csrf
    <x-form.select name="flat" label="Unit" :url="route('options.profit-flats')" :options="$flat ? [$flat->public_id => $flat->project->code.' · '.$flat->building->code.' · Unit '.$flat->flat_no] : []" :selected="$flat?->public_id" required placeholder="Search sold units" />
    <div class="grid gap-5 sm:grid-cols-2">
        <x-form.input name="as_of" type="date" label="As Of" :value="$today" :max="$today" required />
        <x-form.input name="other_cost" label="Other Cost" inputmode="decimal" placeholder="0" />
    </div>
    <x-form.input name="other_cost_note" label="Other Cost Note" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Calculate</x-button>
    </x-form.actions>
</form>
