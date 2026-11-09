@php
    $u = $entry['unit'];
    $p = $entry['position'];
    $isSale = $u instanceof App\Models\FlatSale;
@endphp
<a href="{{ route('customer.flats.show', $u->public_id) }}" class="unit-card block hover:shadow-md">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xl font-semibold text-ink-900">Unit {{ $u->flat->flat_no }}</p>
            <p class="truncate text-sm text-ink-600">{{ $u->project->name }} · {{ $u->flat->building->name }}</p>
        </div>
        @if ($isSale)
            <x-status-badge :status="$u->status" :labels="App\Models\FlatSale::STATUSES" class="shrink-0 whitespace-nowrap" />
        @else
            <x-badge tone="info">Booked</x-badge>
        @endif
    </div>
    <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
        <div><dt class="text-ink-500">{{ $isSale ? 'Contract' : 'Agreed Price' }}</dt><dd class="font-medium tabular-nums text-ink-900">{{ App\Support\Format::money($p['contract'], false) }}</dd></div>
        <div><dt class="text-ink-500">Paid</dt><dd class="font-medium tabular-nums text-success-600">{{ App\Support\Format::money($p['paid'], false) }}</dd></div>
        <div><dt class="text-ink-500">{{ App\Support\Money::cmp($p['balance'], '0') < 0 ? 'Advance' : 'Balance' }}</dt><dd class="font-medium tabular-nums text-ink-900">{{ App\Support\Format::money(ltrim($p['balance'], '-'), false) }}</dd></div>
    </dl>
    @if ($p['next'])
        <p class="mt-3 border-t border-line pt-3 text-[13px] text-ink-600">Next: <span class="font-medium text-ink-900">{{ App\Support\Format::money($p['next']->outstanding()) }}</span> · {{ App\Support\Format::date($p['next']->due_date) }}</p>
    @endif
</a>
