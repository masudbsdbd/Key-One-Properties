@php
    $user = auth()->user();
    $s = $booking->status;
    $pendingCancel = $booking->cancellations->firstWhere('status', 'requested');
@endphp
<x-layouts.app :title="$booking->booking_no">
    <x-page-header :title="$booking->booking_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Bookings' => route('bookings.index'), $booking->booking_no => null]">
        <x-slot:actions>
            @if (in_array($s, ['draft', 'rejected'], true))
                @can('sales.booking.submit')<x-button icon="arrow-right" data-action="post" data-url="{{ route('bookings.submit', $booking) }}" data-reload data-confirm="Submit {{ $booking->booking_no }} for approval?" data-confirm-text="Submit" data-tone="primary">Submit</x-button>@endcan
                @can('sales.booking.edit')<x-button variant="secondary" icon="edit" data-remote-modal="{{ route('bookings.edit', $booking) }}" data-size="max-w-4xl">Edit</x-button>@endcan
            @endif
            @if ($s === 'pending')
                @can('sales.booking.approve')
                    <x-button icon="check" data-action="post" data-url="{{ route('bookings.approve', $booking) }}" data-reload data-confirm="Confirm {{ $booking->booking_no }}?" data-confirm-message="Unit {{ $booking->flat->flat_no }} will be marked booked for {{ $booking->customer->full_name }}." data-confirm-text="Confirm" data-tone="primary">Confirm Booking</x-button>
                    <x-button variant="ghost" data-remote-modal="{{ route('bookings.reason', [$booking, 'reject']) }}" data-size="max-w-md">Reject</x-button>
                @endcan
            @endif
            @if ($s === 'confirmed')
                @can('collections.payment.collect')@if (Route::has('customer-payments.create'))<x-button icon="tag" data-remote-modal="{{ route('customer-payments.create', ['booking' => $booking->public_id]) }}" data-size="max-w-2xl">Receive Money</x-button>@endif @endcan
                @can('sales.sale.create')@if (! $booking->sale)<x-button variant="secondary" icon="door" data-remote-modal="{{ route('sales.create', ['booking' => $booking->public_id]) }}" data-size="max-w-3xl">Convert to Sale</x-button>@endif @endcan
                @can('sales.booking.cancel.request')<x-button variant="ghost" icon="x" data-remote-modal="{{ route('bookings.cancel.form', $booking) }}" data-size="max-w-md" :disabled="(bool) $booking->sale" data-disabled-reason="A sale was created from this booking">Cancel</x-button>@endcan
            @endif
            @if (in_array($s, ['draft', 'pending', 'rejected'], true))
                @can('sales.booking.edit')<x-button variant="ghost" icon="x" data-remote-modal="{{ route('bookings.reason', [$booking, 'withdraw']) }}" data-size="max-w-md" aria-label="Withdraw booking" />@endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($pendingCancel)
        <div class="mb-6 rounded-card border border-warning-600/30 bg-warning-50 px-5 py-3 text-sm text-warning-600">Cancellation {{ $pendingCancel->cancellation_no }} is waiting for approval · {{ $pendingCancel->reason }}</div>
    @endif
    @if ($booking->status_reason && in_array($s, ['rejected', 'cancelled'], true))
        <div class="mb-6 rounded-card border border-danger-500/30 bg-danger-50 px-5 py-3 text-sm text-danger-600">{{ $booking->status_reason }}</div>
    @endif

    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        <button type="button" role="tab" class="tab" id="tab-overview" data-tab="overview" aria-controls="panel-overview">Overview</button>
        <button type="button" role="tab" class="tab" id="tab-payments" data-tab="payments" aria-controls="panel-payments">Payments</button>
        <button type="button" role="tab" class="tab" id="tab-files" data-tab="files" aria-controls="panel-files">Files</button>
        <button type="button" role="tab" class="tab" id="tab-history" data-tab="history" aria-controls="panel-history">History</button>
    </div>

    <section id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" class="grid gap-6 xl:grid-cols-3">
        <x-card class="min-w-0 xl:col-span-2">
            <div class="grid gap-5 sm:grid-cols-2">
                <div><p class="detail-label">Unit</p><p class="detail-value font-semibold">{{ $booking->project->name }} · Unit {{ $booking->flat->flat_no }}</p><p class="text-[13px] text-ink-500">{{ $booking->flat->building->name }} · {{ $booking->flat->floor->label }} · {{ number_format((float) $booking->flat->saleable_area) }} {{ $booking->flat->area_unit }}</p></div>
                <div><p class="detail-label">Customer</p><a href="{{ route('customers.show', $booking->customer) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $booking->customer->full_name }}</a><p class="text-[13px] text-ink-500">{{ $booking->customer->customer_code }} · {{ App\Support\Phone::display($booking->customer->primary_mobile) }}</p></div>
                <div><p class="detail-label">Booking Date</p><p class="detail-value">{{ App\Support\Format::date($booking->booking_date) }}</p></div>
                <div><p class="detail-label">Hold Until</p><p class="detail-value">{{ App\Support\Format::date($booking->booking_expires_at) }}</p></div>
                <div><p class="detail-label">Status</p><x-status-badge :status="$s" :labels="App\Models\FlatBooking::STATUSES" /></div>
                <div><p class="detail-label">Sale</p>@if ($booking->sale)<a href="{{ route('sales.show', $booking->sale) }}" class="detail-value text-accent-700 hover:underline">{{ $booking->sale->sale_no }}</a>@else<p class="detail-value">—</p>@endif</div>
            </div>
            @if ($booking->notes)<p class="mt-4 whitespace-pre-line border-t border-line pt-4 text-sm text-ink-700">{{ $booking->notes }}</p>@endif
        </x-card>
        <x-card title="Price">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-ink-500">Asking Price (at booking)</dt><dd><x-money :value="$booking->asking_price_snapshot" muted /></dd></div>
                <div class="flex justify-between"><dt class="font-medium text-ink-900">Agreed Price</dt><dd class="font-semibold"><x-money :value="$booking->agreed_price" /></dd></div>
                <div class="flex justify-between"><dt class="text-ink-500">Booking Money</dt><dd><x-money :value="$booking->booking_amount_expected" /></dd></div>
                <div class="flex justify-between border-t border-line pt-2"><dt class="text-ink-500">Received</dt><dd class="font-medium"><x-money :value="$refundable" /></dd></div>
            </dl>
        </x-card>
    </section>

    <section id="panel-payments" role="tabpanel" aria-labelledby="tab-payments" hidden>
        <x-card :padded="false">
            @forelse ($booking->payments as $p)
                <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0">
                    <span class="min-w-0 flex-1"><span class="block font-medium text-ink-900">{{ $p->payment_no }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($p->payment_date) }} · {{ App\Models\CustomerPayment::METHODS[$p->method] ?? $p->method }}</span></span>
                    <x-money :value="$p->amount" class="font-medium" />
                    <x-status-badge :status="$p->status" />
                </div>
            @empty
                <x-empty-state icon="tag" title="No payments" />
            @endforelse
        </x-card>
    </section>

    <section id="panel-files" role="tabpanel" aria-labelledby="tab-files" hidden>
        <x-attachments :owner="$booking" type="booking" />
    </section>

    <section id="panel-history" role="tabpanel" aria-labelledby="tab-history" hidden>
        <x-card :padded="false">@include('projects.partials._activity', ['entries' => $activity])</x-card>
    </section>
</x-layouts.app>
