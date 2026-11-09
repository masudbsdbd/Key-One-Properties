<form method="POST" action="{{ $action }}" data-ajax-form data-refresh-partials class="space-y-5">
    @csrf
    @method('PUT')
    <div class="grid gap-5 sm:grid-cols-[140px_1fr]">
        <x-form.input name="code" label="Code" :value="$record->code" maxlength="20" required />
        <x-form.input name="name" label="Name" :value="$record->name" required />
    </div>
    @if ($kind === 'unit')
        <x-form.select name="precision" label="Decimal Places" :options="[0 => '0 (whole numbers)', 1 => '1', 2 => '2', 3 => '3']" :selected="(string) $record->precision" required :enhance="false" />
    @endif
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save</x-button>
    </x-form.actions>
</form>
