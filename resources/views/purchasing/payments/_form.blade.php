@php
    $today = now(config('app.display_timezone'))->toDateString();
    $supplierOptions = $supplier ? [$supplier->public_id => $supplier->name.' · '.$supplier->supplier_code] : [];
@endphp
<form method="POST" action="{{ route('supplier-payments.store') }}" data-ajax-form class="space-y-5" data-payment-form>
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    <div class="grid gap-5 sm:grid-cols-3">
        <x-form.select name="supplier" id="payment-supplier" label="Supplier" :url="route('options.suppliers')" :options="$supplierOptions" :selected="$supplier?->public_id" required placeholder="Search suppliers" wrapper-class="sm:col-span-2" />
        <x-form.input name="payment_date" type="date" label="Date" :value="$today" :max="$today" required />
        <x-form.input name="amount" label="Amount" inputmode="decimal" placeholder="0.00" required data-payment-amount />
        <x-form.select name="payment_method" label="Method" :options="App\Models\SupplierPayment::METHODS" selected="bank" required />
        <x-form.input name="payment_reference" label="Reference" />
    </div>

    <div>
        <div class="mb-1.5 flex items-center justify-between"><p class="form-label mb-0">Allocate to Bills</p><span class="text-[13px] text-ink-500">Unallocated <span class="font-medium tabular-nums text-ink-900" data-payment-unallocated>৳ 0.00</span></span></div>
        <div class="rounded-card border border-line" data-partial-source="#payment-supplier" data-partial-base="{{ route('supplier-payments.allocatable') }}" data-partial-param="supplier" data-partial-empty="Select a supplier"><p class="p-5 text-sm text-ink-500">Select a supplier</p></div>
        <p class="form-error" data-field-error="allocations" hidden></p>
    </div>

    <x-form.textarea name="notes" label="Notes" rows="2" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Record Payment</x-button>
    </x-form.actions>
</form>
