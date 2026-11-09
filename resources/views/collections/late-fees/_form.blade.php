@php($customerOptions = $customer ? [$customer->public_id => $customer->full_name.' · '.$customer->customer_code] : [])
<form method="POST" action="{{ route('late-fees.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    <x-form.select name="customer" id="lf-customer" label="Customer" :url="route('options.customers')" :options="$customerOptions" :selected="$customer?->public_id" required placeholder="Search customers" />
    <x-form.select name="sale" label="Sale" :url="route('options.customer-sales')" depends-on="#lf-customer" depends-param="customer" placeholder="All sales of this customer" />
    <x-form.select name="fee_type" label="Fee" :options="App\Models\CustomerLateFeePolicy::TYPES" selected="fixed" required :enhance="false" />
    <x-form.input name="fee_value" label="Amount or %" inputmode="decimal" />
    <x-form.input name="grace_days" type="number" label="Grace Days" value="0" min="0" max="365" />
    <x-form.select name="frequency" label="Charge" :options="App\Models\CustomerLateFeePolicy::FREQUENCIES" selected="once" required :enhance="false" />
    <x-form.input name="effective_from" type="date" label="From" :value="now(config('app.display_timezone'))->toDateString()" required />
    <x-form.input name="effective_to" type="date" label="Until" />
    <x-form.actions class="sm:col-span-2 mt-0"><x-button variant="secondary" data-modal-close>Cancel</x-button><x-button type="submit" icon="check">Save</x-button></x-form.actions>
</form>
