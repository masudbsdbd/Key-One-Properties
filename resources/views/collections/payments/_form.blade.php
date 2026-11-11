@php($today = now(config('app.display_timezone'))->toDateString())
@php($doc = $sale ?? $booking)
<form method="POST" action="{{ route('customer-payments.store', $sale ? ['sale' => $sale->public_id] : ['booking' => $booking->public_id]) }}" data-ajax-form class="space-y-5" data-payment-preview="{{ $sale ? route('customer-payments.preview', ['sale' => $sale->public_id]) : '' }}">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    <div class="rounded-card border border-line bg-surface-muted px-4 py-3 text-sm">
        <p class="font-semibold text-ink-900">{{ $doc->customer->full_name }} · Unit {{ $doc->flat->flat_no }}</p>
        <p class="text-ink-500">{{ $sale ? 'Sale '.$sale->sale_no : 'Booking money · '.$booking->booking_no }}</p>
    </div>
    <div class="grid gap-5 sm:grid-cols-2">
        <x-form.input name="amount" label="Amount" inputmode="decimal" required autofocus data-payment-amount />
        <x-form.input name="payment_date" type="date" label="Date" :value="$today" :max="$today" required />
        <x-form.select name="method" label="Method" :options="App\Models\CustomerPayment::METHODS" selected="cash" required />
        <x-form.money-account label="Received Into" method="#f-method" />
        <x-form.input name="reference_no" label="Reference" />
    </div>
    @if ($sale)<div data-payment-allocation></div>@endif
    <x-form.textarea name="notes" label="Notes" rows="2" />
    <x-form.actions class="mt-0"><x-button variant="secondary" data-modal-close>Cancel</x-button><x-button type="submit" icon="check">Record Payment</x-button></x-form.actions>
</form>
