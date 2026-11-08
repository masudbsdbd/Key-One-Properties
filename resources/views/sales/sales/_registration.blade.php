@php($r = $sale->registration)
<form method="POST" action="{{ route('sales.stage.update', [$sale, 'registration']) }}" data-ajax-form data-reload-on-success class="grid gap-5 sm:grid-cols-2">
    @csrf
    <x-form.select name="status" label="Status" :options="App\Models\FlatRegistration::STATUSES" :selected="$r->status" required :enhance="false" />
    <x-form.input name="registration_no" label="Registration No." :value="$r->registration_no" />
    <x-form.input name="registered_at" type="date" label="Registered On" :value="$r->registered_at?->toDateString()" :max="now(config('app.display_timezone'))->toDateString()" />
    <x-form.input name="registration_cost" label="Registration Cost" :value="$r->registration_cost" inputmode="decimal" />
    <x-form.textarea name="notes" label="Notes" rows="2" :value="$r->notes" wrapper-class="sm:col-span-2" />
    <x-form.actions class="sm:col-span-2 mt-0"><x-button variant="secondary" data-modal-close>Cancel</x-button><x-button type="submit" icon="check">Save</x-button></x-form.actions>
</form>
