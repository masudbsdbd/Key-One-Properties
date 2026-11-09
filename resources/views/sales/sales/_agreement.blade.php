<form method="POST" action="{{ route('sales.stage.update', [$sale, 'agreement']) }}" data-ajax-form data-reload-on-success class="space-y-5">
    @csrf
    <p class="text-sm text-ink-700">{{ $sale->agreement->agreement_no }}</p>
    <x-form.input name="signed_at" type="date" label="Signed On" :value="$sale->agreement->signed_at?->toDateString()" :max="now(config('app.display_timezone'))->toDateString()" />
    <x-form.textarea name="notes" label="Notes" rows="3" :value="$sale->agreement->notes" />
    <x-form.actions class="mt-0"><x-button variant="secondary" data-modal-close>Cancel</x-button><x-button type="submit" icon="check">Save</x-button></x-form.actions>
</form>
