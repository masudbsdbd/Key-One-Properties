@php
    $today = now(config('app.display_timezone'))->toDateString();
    $orderOptions = $order ? [$order->public_id => $order->wo_no.' · '.$order->title.' · '.$order->contractor->name] : [];
    $refund ??= null;
@endphp
<form method="POST" action="{{ route('contractor-payments.store') }}" data-ajax-form class="space-y-5">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    @if ($refund !== null)
        <input type="hidden" name="kind" value="refund">
        <input type="hidden" name="work_order" value="{{ $order->public_id }}">
        <div class="grid gap-5 sm:grid-cols-2">
            <div><p class="detail-label">Contractor</p><p class="detail-value">{{ $order->contractor->name }}</p></div>
            <div><p class="detail-label">Unused Advance</p><p class="detail-value"><x-money :value="$refund" /></p></div>
        </div>
    @else
        <x-form.select name="work_order" id="cpay-order" label="Work Order" :url="route('options.work-orders')" :options="$orderOptions" :selected="$order?->public_id" required placeholder="Search work orders" />
        <div data-partial-source="#cpay-order" data-partial-base="{{ route('work-orders.payable', $bill ? ['bill' => $bill->public_id] : []) }}" data-partial-param="work_order"></div>
    @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <x-form.input name="payment_date" type="date" label="Date" :value="$today" :max="$today" required />
        <x-form.input name="amount" label="Amount" inputmode="decimal" placeholder="0.00" :value="$refund ?? $bill?->outstanding(true)" required />
        <x-form.select name="payment_method" label="Method" :options="App\Models\ContractorPayment::METHODS" selected="bank" required />
        <x-form.money-account label="Account" />
        <x-form.input name="tax_deducted" label="Tax Deducted (BDT)" inputmode="decimal" />
        <x-form.input name="reference" label="Reference" />
    </div>
    <x-form.textarea name="notes" label="Notes" rows="2" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $refund !== null ? 'Record Refund' : 'Record Payment' }}</x-button>
    </x-form.actions>
</form>
