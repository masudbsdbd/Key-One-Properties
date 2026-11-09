@php
    $tones = ['available' => 'border-success-600/40 bg-success-50 text-success-600 hover:border-success-600', 'booked' => 'border-info-600/30 bg-info-50 text-info-600',
        'sold' => 'border-violet-600/30 bg-violet-50 text-violet-600', 'unavailable' => 'border-line bg-surface-muted text-ink-500', 'maintenance' => 'border-warning-600/30 bg-warning-50 text-warning-600',
        'not_released' => 'border-dashed border-line-strong bg-surface text-ink-400'];
    $canBook = auth()->user()->can('sales.booking.create');
@endphp
<div class="space-y-6">
    @forelse ($buildings as $building)
        <x-card :title="$building->name.' ('.$building->code.')'" :padded="false">
            <div class="divide-y divide-line">
                @foreach ($building->floors as $floor)
                    @continue($floor->flats->isEmpty())
                    <div class="flex flex-col gap-3 px-5 py-3 sm:flex-row sm:items-center">
                        <span class="w-28 shrink-0 text-[13px] font-medium text-ink-500">{{ $floor->label }}</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($floor->flats as $flat)
                                @php($holder = $flat->claim?->claimant)
                                @php($tip = 'Unit '.$flat->flat_no.' · '.App\Models\Flat::STATUSES[$flat->status].' · '.App\Support\Format::money($flat->list_price, false).($holder ? ' · '.($holder->booking_no ?? $holder->sale_no) : ''))
                                @if ($flat->status === 'available' && $canBook)
                                    <button type="button" class="min-w-16 rounded-lg border px-3 py-2 text-sm font-semibold {{ $tones[$flat->status] }}" data-remote-modal="{{ route('bookings.create', ['flat' => $flat->public_id]) }}" data-size="max-w-4xl" data-tooltip="{{ $tip }}" aria-label="Book unit {{ $flat->flat_no }}">{{ $flat->flat_no }}</button>
                                @elseif ($holder)
                                    <a href="{{ $holder instanceof App\Models\FlatSale ? route('sales.show', $holder) : route('bookings.show', $holder) }}" class="min-w-16 rounded-lg border px-3 py-2 text-center text-sm font-semibold {{ $tones[$flat->status] ?? $tones['unavailable'] }}" data-tooltip="{{ $tip }}">{{ $flat->flat_no }}</a>
                                @else
                                    <span class="min-w-16 rounded-lg border px-3 py-2 text-center text-sm font-semibold {{ $tones[$flat->status] ?? $tones['unavailable'] }}" data-tooltip="{{ $tip }}">{{ $flat->flat_no }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @empty
        <x-card :padded="false"><x-empty-state icon="building" title="No units in this project" /></x-card>
    @endforelse
</div>
