@php($h = $sale->handover)
<form method="POST" action="{{ route('sales.stage.update', [$sale, 'handover']) }}" data-ajax-form data-reload-on-success class="grid gap-5 sm:grid-cols-2">
    @csrf
    <x-form.select name="status" label="Status" :options="App\Models\FlatHandover::STATUSES" :selected="$h->status" required :enhance="false" />
    <x-form.input name="scheduled_at" type="date" label="Scheduled For" :value="$h->scheduled_at?->toDateString()" />
    <x-form.input name="completed_at" type="date" label="Handed Over On" :value="$h->completed_at?->toDateString()" :max="now(config('app.display_timezone'))->toDateString()" />
    <div class="sm:col-span-2">
        <p class="form-label">Checklist</p>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach (App\Models\FlatHandover::CHECKLIST as $key => $label)
                <x-form.checkbox name="checklist[]" :value="$key" :label="$label" :checked="in_array($key, $h->checklist_json ?? [], true)" />
            @endforeach
            <p class="form-error sm:col-span-2" data-field-error="checklist" hidden></p>
        </div>
    </div>
    <x-form.textarea name="notes" label="Notes" rows="2" :value="$h->notes" wrapper-class="sm:col-span-2" />
    <x-form.actions class="sm:col-span-2 mt-0"><x-button variant="secondary" data-modal-close>Cancel</x-button><x-button type="submit" icon="check">Save</x-button></x-form.actions>
</form>
