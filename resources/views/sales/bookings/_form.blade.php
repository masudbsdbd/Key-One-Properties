@php
    $today = now(config('app.display_timezone'))->toDateString();
    $flatOptions = $booking->flat ? [$booking->flat->public_id => ($booking->flat->project->code ?? '').' · '.($booking->flat->building->code ?? '').' · Unit '.$booking->flat->flat_no] : [];
    $customerOptions = $booking->customer ? [$booking->customer->public_id => $booking->customer->full_name.' · '.$booking->customer->customer_code] : [];
@endphp
<form method="POST" action="{{ $booking->exists ? route('bookings.update', $booking) : route('bookings.store') }}" data-ajax-form class="space-y-5" data-sale-form>
    @csrf
    @if ($booking->exists) @method('PUT') @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <x-form.select name="flat" id="booking-flat" label="Unit" :url="route('options.sale-flats')" :options="$flatOptions" :selected="$booking->flat?->public_id" required placeholder="Search available units" />
        <x-form.select name="customer" label="Customer" :url="route('options.customers')" :options="$customerOptions" :selected="$booking->customer?->public_id" required placeholder="Search customers" />
    </div>
    <div data-partial-source="#booking-flat" data-partial-base="{{ route('sales.flat-summary') }}" data-partial-param="flat"></div>
    <div class="grid gap-5 sm:grid-cols-4">
        <x-form.input name="agreed_price" label="Agreed Price" :value="$booking->agreed_price" inputmode="decimal" required data-agreed-price wrapper-class="sm:col-span-2" />
        <x-form.input name="booking_amount_expected" label="Booking Money" :value="$booking->booking_amount_expected" inputmode="decimal" placeholder="0" wrapper-class="sm:col-span-2" />
        <x-form.input name="booking_date" type="date" label="Booking Date" :value="$booking->booking_date?->toDateString() ?? $today" :max="$today" required wrapper-class="sm:col-span-2" />
        <x-form.input name="booking_expires_at" type="date" label="Hold Until" :value="$booking->booking_expires_at?->toDateString()" wrapper-class="sm:col-span-2" />
    </div>
    <x-form.textarea name="notes" label="Terms & Notes" rows="2" :value="$booking->notes" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        @if ($booking->exists)
            <x-button type="submit" icon="check">Save</x-button>
        @else
            <x-button type="submit" variant="secondary">Save Draft</x-button>
            @can('sales.booking.submit')<x-button type="submit" name="submit" value="1" icon="arrow-right">Submit</x-button>@endcan
        @endif
    </x-form.actions>
</form>
