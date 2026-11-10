@php
    $actor = auth()->user();
    $selectable = $actor->can('flats.change_status');
@endphp
@forelse ($flats as $flat)
    <tr>
        @if ($selectable)<td><input type="checkbox" class="form-check" value="{{ $flat->public_id }}" data-select-row aria-label="Select {{ $flat->flat_no }}"></td>@endif
        <td><a href="{{ route('flats.show', $flat) }}" class="font-semibold text-ink-900 hover:text-accent-700">{{ $flat->flat_no }}</a></td>
        @if ($showProject)<td class="whitespace-nowrap text-ink-600"><x-ref :to="$flat->project">{{ $flat->project->name }}</x-ref></td>@endif
        <td class="whitespace-nowrap text-ink-600">{{ $flat->building->code }} · {{ $flat->floor->label }}</td>
        <td class="text-ink-600">{{ $flat->flat_type ?: '—' }}@if ($flat->bedrooms !== null)<span class="text-ink-400"> · {{ $flat->bedrooms }}B/{{ $flat->bathrooms ?? 0 }}T</span>@endif</td>
        <td class="whitespace-nowrap text-right tabular-nums">{{ number_format((float) $flat->saleable_area, 0) }} {{ $flat->area_unit }}</td>
        <td class="whitespace-nowrap text-right tabular-nums">{{ App\Support\Format::money($flat->list_price, false) }}</td>
        <td><x-badge :tone="App\Models\Flat::STATUS_TONES[$flat->status] ?? 'neutral'">{{ App\Models\Flat::STATUSES[$flat->status] ?? $flat->status }}</x-badge>@if ($flat->deal)<x-ref :to="$flat->deal" class="mt-0.5 block max-w-44 truncate text-[12px] text-ink-600">{{ $flat->deal->customer?->full_name }}</x-ref>@endif</td>
        <td class="whitespace-nowrap text-right">
            @php
                $canPrice = $actor->can('changePrice', $flat);
                $canStatus = ! $flat->isLockedBySales() && $actor->can('changeStatus', $flat);
                $canDelete = $actor->can('delete', $flat);
                $item = 'flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-muted';
            @endphp
            @can('update', $flat)
                <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('flats.edit', $flat) }}" data-size="max-w-3xl" aria-label="Edit {{ $flat->flat_no }}"><x-icon name="edit" size="h-4 w-4" /></button>
            @endcan
            @if ($canPrice || $canStatus || $canDelete)
                <button type="button" class="btn btn-ghost btn-icon" data-menu-button aria-controls="unit-menu-{{ $flat->public_id }}" aria-expanded="false" aria-label="More for {{ $flat->flat_no }}"><x-icon name="more" size="h-4 w-4" /></button>
                <div id="unit-menu-{{ $flat->public_id }}" data-menu data-menu-float hidden class="fixed z-30 w-48 rounded-card border border-line bg-surface p-1.5 text-left shadow-raised">
                    @if ($canPrice)<button type="button" class="{{ $item }} text-ink-700" data-remote-modal="{{ route('flats.price.edit', $flat) }}" data-size="max-w-md"><x-icon name="tag" size="h-4 w-4 text-ink-400" />Change Price</button>@endif
                    @if ($canStatus)<button type="button" class="{{ $item }} text-ink-700" data-remote-modal="{{ route('flats.status.edit', $flat) }}" data-size="max-w-md"><x-icon name="refresh" size="h-4 w-4 text-ink-400" />Change Status</button>@endif
                    @if ($canDelete)<button type="button" class="{{ $item }} text-danger-600" data-row-action="delete" data-url="{{ route('flats.destroy', $flat) }}" data-confirm="Delete unit {{ $flat->flat_no }}?" data-confirm-text="Delete"><x-icon name="trash" size="h-4 w-4" />Delete</button>@endif
                </div>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="{{ ($showProject ? 8 : 7) + ($selectable ? 1 : 0) }}"><x-empty-state icon="door" title="No units found" /></td></tr>
@endforelse
