@php($today = now(config('app.display_timezone'))->toDateString())
<form method="POST" action="{{ route('refunds.pay', $refund) }}" data-ajax-form data-reload-on-success class="space-y-5">
    @csrf
    <div class="flex items-center justify-between rounded-card bg-surface-muted px-4 py-3 text-sm"><span class="text-ink-500">{{ $refund->customer->full_name }}</span><x-money :value="$refund->payable()" class="font-semibold text-ink-900" /></div>
    <div class="grid gap-5 sm:grid-cols-2">
        <x-form.input name="paid_on" type="date" label="Paid On" :value="$today" :max="$today" required />
        <x-form.select name="payment_method" label="Method" :options="App\Models\CustomerPayment::METHODS" selected="bank" required />
        <x-form.input name="payment_reference" label="Reference" wrapper-class="sm:col-span-2" />
    </div>
    <x-form.actions class="mt-0"><x-button variant="secondary" data-modal-close>Cancel</x-button><x-button type="submit" icon="check">Record Payment</x-button></x-form.actions>
</form>
