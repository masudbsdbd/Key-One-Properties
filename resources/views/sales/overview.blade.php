@php($user = auth()->user())
<x-layouts.app title="Sales Overview">
    <x-page-header title="Sales Overview" :breadcrumbs="['Dashboard' => route('dashboard'), 'Sales' => null]">
        <x-slot:actions>
            @can('sales.booking.create')<x-button icon="plus" data-remote-modal="{{ route('bookings.create') }}" data-size="max-w-4xl">New Booking</x-button>@endcan
            <x-button variant="secondary" icon="grid" href="{{ route('sales.availability') }}">Availability</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Available Units" :value="number_format($counts['available'] ?? 0)" icon="door" tone="success" :href="route('sales.availability')" />
        <x-stat-card label="Booked" :value="number_format($counts['booked'] ?? 0)" icon="calendar-check" tone="info" :href="route('bookings.index')" />
        <x-stat-card label="Sold" :value="number_format($counts['sold'] ?? 0)" icon="check" tone="accent" :href="route('sales.index')" />
        <x-stat-card label="Awaiting Approval" :value="number_format($pendingBookings + $pendingSales)" icon="clock" tone="warning" />
    </div>
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Contract Value" :value="App\Support\Format::money($salesValue, false)" icon="tag" tone="accent" />
        <x-stat-card label="Sales This Month" :value="App\Support\Format::money($monthSales, false)" icon="bar-chart" tone="info" />
        <x-stat-card label="Collected" :value="App\Support\Format::money($collected, false)" icon="check-square" tone="success" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-card title="Recent Bookings" :padded="false">
            @forelse ($recentBookings as $b)
                <a href="{{ route('bookings.show', $b) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0 hover:bg-surface-muted">
                    <span class="min-w-0 flex-1"><span class="block font-medium text-ink-900">{{ $b->booking_no }} · {{ $b->customer->full_name }}</span><span class="block text-[13px] text-ink-500">{{ $b->project->code }} · Unit {{ $b->flat->flat_no }} · {{ App\Support\Format::date($b->booking_date) }}</span></span>
                    <x-money :value="$b->agreed_price" class="text-sm" />
                    <x-status-badge :status="$b->status" :labels="App\Models\FlatBooking::STATUSES" />
                </a>
            @empty
                <x-empty-state icon="calendar-check" title="No bookings yet" />
            @endforelse
        </x-card>
        <x-card title="Recent Sales" :padded="false">
            @forelse ($recentSales as $s)
                <a href="{{ route('sales.show', $s) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0 hover:bg-surface-muted">
                    <span class="min-w-0 flex-1"><span class="block font-medium text-ink-900">{{ $s->sale_no }} · {{ $s->customer->full_name }}</span><span class="block text-[13px] text-ink-500">{{ $s->project->code }} · Unit {{ $s->flat->flat_no }} · {{ App\Support\Format::date($s->agreement_date) }}</span></span>
                    <x-money :value="$s->net_contract_value" class="text-sm" />
                    <x-status-badge :status="$s->status" :labels="App\Models\FlatSale::STATUSES" />
                </a>
            @empty
                <x-empty-state icon="door" title="No sales yet" />
            @endforelse
        </x-card>
    </div>
</x-layouts.app>
