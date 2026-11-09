<form method="POST" action="{{ route('investor-agreements.reason.store', [$agreement, 'close']) }}" data-ajax-form data-reload-on-success class="space-y-5">
    @csrf
    <x-form.input name="effective_to" type="date" label="End Date" :value="now(config('app.display_timezone'))->toDateString()" :min="$agreement->effective_from->toDateString()" required />
    <x-form.textarea name="reason" label="Reason" rows="3" required />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" variant="danger">Close Agreement</x-button>
    </x-form.actions>
</form>
