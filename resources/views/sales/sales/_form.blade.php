@php
    $today = now(config('app.display_timezone'))->toDateString();
    $fromBooking = (bool) $sale->booking;
    $flatOptions = $sale->flat ? [$sale->flat->public_id => ($sale->flat->project->code ?? '').' · Unit '.$sale->flat->flat_no] : [];
    $customerOptions = $sale->customer ? [$sale->customer->public_id => $sale->customer->full_name.' · '.$sale->customer->customer_code] : [];
@endphp
<form method="POST" action="{{ $sale->exists ? route('sales.update', $sale) : route('sales.store') }}" data-ajax-form class="space-y-5">
    @csrf
    @if ($sale->exists) @method('PUT') @endif
    @if ($fromBooking)
        <input type="hidden" name="booking" value="{{ $sale->booking->public_id }}">
        <div class="rounded-card border border-line bg-surface-muted p-4 text-sm">
            <p class="font-semibold text-ink-900">{{ $sale->booking->booking_no }} · Unit {{ $sale->flat->flat_no }}</p>
            <p class="text-ink-500">{{ $sale->customer->full_name }} · Agreed <x-money :value="$sale->booking->agreed_price" /></p>
        </div>
    @else
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.select name="flat" id="sale-flat" label="Unit" :url="route('options.sale-flats')" :options="$flatOptions" :selected="$sale->flat?->public_id" required placeholder="Search available units" />
            <x-form.select name="customer" label="Customer" :url="route('options.customers')" :options="$customerOptions" :selected="$sale->customer?->public_id" required placeholder="Search customers" />
        </div>
        <div data-partial-source="#sale-flat" data-partial-base="{{ route('sales.flat-summary') }}" data-partial-param="flat"></div>
    @endif
    <div class="grid gap-5 sm:grid-cols-3">
        <x-form.input name="sale_price" label="Sale Price" :value="$sale->sale_price" inputmode="decimal" required data-calc-price />
        <x-form.input name="discount_amount" label="Discount" :value="(float) $sale->discount_amount ? $sale->discount_amount : null" inputmode="decimal" placeholder="0" />
        <x-form.input name="agreement_date" type="date" label="Agreement Date" :value="$sale->agreement_date?->toDateString() ?? $today" :max="$today" required />
    </div>
    <x-form.textarea name="notes" label="Notes" rows="2" :value="$sale->notes" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        @if ($sale->exists)
            <x-button type="submit" icon="check">Save</x-button>
        @else
            <x-button type="submit" variant="secondary">Save Draft</x-button>
            <x-button type="submit" name="submit" value="1" icon="arrow-right">Submit</x-button>
        @endif
    </x-form.actions>
</form>
