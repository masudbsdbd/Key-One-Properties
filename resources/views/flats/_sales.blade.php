@php
    $current = $sales['current'];
    $position = $sales['position'];
    $isSale = $current instanceof App\Models\FlatSale;
@endphp
<x-card title="Sales">
    @if ($current)
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <a href="{{ $isSale ? route('sales.show', $current) : route('bookings.show', $current) }}" class="font-semibold text-ink-900 hover:text-accent-700">{{ $isSale ? $current->sale_no : $current->booking_no }}</a>
                <p class="text-[13px] text-ink-500">{{ $isSale ? 'Sale' : 'Booking' }} · {{ App\Support\Format::date($isSale ? $current->agreement_date : $current->booking_date) }}</p>
            </div>
            <x-status-badge :status="$current->status" :labels="$isSale ? App\Models\FlatSale::STATUSES : App\Models\FlatBooking::STATUSES" />
        </div>
        <div class="mt-4 flex items-center gap-3 rounded-control border border-line p-3">
            <x-avatar :name="$current->customer->full_name" size="h-9 w-9 text-sm" />
            <div class="min-w-0">
                @can('view', $current->customer)
                    <a href="{{ route('customers.show', $current->customer) }}" class="block truncate font-medium text-ink-900 hover:text-accent-700">{{ $current->customer->full_name }}</a>
                @else
                    <p class="truncate font-medium text-ink-900">{{ $current->customer->full_name }}</p>
                @endcan
                <p class="text-[13px] text-ink-500">{{ $current->customer->customer_code }}</p>
            </div>
        </div>
        <dl class="mt-4 space-y-3">
            <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">{{ $isSale ? 'Contract Value' : 'Agreed Price' }}</dt><dd class="font-semibold tabular-nums text-ink-900"><x-money :value="$isSale ? $current->net_contract_value : $current->agreed_price" /></dd></div>
            @if ($position)
                <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Paid</dt><dd class="font-medium tabular-nums text-success-600"><x-money :value="$position['paid']" /></dd></div>
                <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Balance</dt><dd class="font-medium tabular-nums text-ink-900"><x-money :value="$position['balance']" /></dd></div>
                @if (App\Support\Money::isPositive($position['overdue']))
                    <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Overdue</dt><dd class="font-medium tabular-nums text-danger-600"><x-money :value="$position['overdue']" /></dd></div>
                @endif
                @if ($position['next'])
                    <div class="flex items-center justify-between gap-4"><dt class="text-sm text-ink-500">Next Due</dt><dd class="text-sm text-ink-900">{{ App\Support\Format::date($position['next']->due_date) }} · <x-money :value="$position['next']->outstanding()" /></dd></div>
                @endif
            @endif
        </dl>
    @else
        <x-empty-state icon="calendar" title="No sale" class="py-6">
            @if ($flat->status === 'available')
                @can('sales.booking.create')
                    <x-button size="sm" icon="plus" data-remote-modal="{{ route('bookings.create', ['flat' => $flat->public_id]) }}" data-size="max-w-3xl">New Booking</x-button>
                @endcan
            @endif
        </x-empty-state>
    @endif

    @if ($sales['history']->isNotEmpty())
        <div class="mt-5 border-t border-line pt-4">
            <p class="detail-label">Earlier</p>
            <ul class="mt-2 space-y-2">
                @foreach ($sales['history'] as $h)
                    @php $hSale = $h instanceof App\Models\FlatSale; @endphp
                    <li class="flex items-center justify-between gap-3 text-sm">
                        <span class="min-w-0 truncate"><a href="{{ $hSale ? route('sales.show', $h) : route('bookings.show', $h) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $hSale ? $h->sale_no : $h->booking_no }}</a> <span class="text-ink-500">· {{ $h->customer->full_name }}</span></span>
                        <x-status-badge :status="$h->status" :labels="$hSale ? App\Models\FlatSale::STATUSES : App\Models\FlatBooking::STATUSES" />
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-card>
