@php($today = now(config('app.display_timezone'))->toDateString())
<form method="POST" action="{{ route('work-orders.measurements.store', $order) }}" data-ajax-form data-reload-on-success class="grid gap-5 sm:grid-cols-4" data-calc-form>
    @csrf
    <x-form.input name="description" label="Work Done" required autofocus wrapper-class="sm:col-span-3" placeholder="3rd floor slab casting" />
    <x-form.input name="measurement_date" type="date" label="Date" :value="$today" :max="$today" required />
    <x-form.input name="quantity" label="Quantity" inputmode="decimal" required data-calc />
    <x-form.input name="unit" label="Unit" required placeholder="sft, cft, rft, job" />
    <x-form.input name="rate" label="Rate" inputmode="decimal" required data-calc />
    <div><p class="form-label">Amount</p><p class="flex h-10 items-center font-semibold tabular-nums text-ink-900" data-calc-total>৳ 0.00</p></div>
    <p class="text-[13px] text-ink-500 sm:col-span-4">Remaining contract value <x-money :value="$remaining" class="font-medium text-ink-900" /></p>
    <x-form.actions class="sm:col-span-4 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save</x-button>
    </x-form.actions>
</form>
