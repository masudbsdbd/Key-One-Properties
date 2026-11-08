@php($actor = auth()->user())
@forelse ($flats as $flat)
    <tr>
        <td><a href="{{ route('flats.show', $flat) }}" class="font-semibold text-ink-900 hover:text-accent-700">{{ $flat->flat_no }}</a></td>
        @if ($showProject)<td class="text-ink-600">{{ $flat->project->name }}</td>@endif
        <td class="whitespace-nowrap text-ink-600">{{ $flat->building->code }} · {{ $flat->floor->label }}</td>
        <td class="text-ink-600">{{ $flat->flat_type ?: '—' }}@if ($flat->bedrooms !== null)<span class="text-ink-400"> · {{ $flat->bedrooms }}B/{{ $flat->bathrooms ?? 0 }}T</span>@endif</td>
        <td class="whitespace-nowrap text-right tabular-nums">{{ number_format((float) $flat->saleable_area, 0) }} {{ $flat->area_unit }}</td>
        <td class="whitespace-nowrap text-right tabular-nums">{{ App\Support\Format::money($flat->list_price, false) }}</td>
        <td><x-badge :tone="App\Models\Flat::STATUS_TONES[$flat->status] ?? 'neutral'">{{ App\Models\Flat::STATUSES[$flat->status] ?? $flat->status }}</x-badge></td>
        <td class="whitespace-nowrap text-right">
            @can('changePrice', $flat)
                <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('flats.price.edit', $flat) }}" data-size="max-w-md" aria-label="Change price of {{ $flat->flat_no }}"><x-icon name="tag" size="h-4 w-4" /></button>
            @endcan
            @if (! $flat->isLockedBySales() && $actor->can('changeStatus', $flat))
                <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('flats.status.edit', $flat) }}" data-size="max-w-md" aria-label="Change status of {{ $flat->flat_no }}"><x-icon name="refresh" size="h-4 w-4" /></button>
            @endif
            @can('update', $flat)
                <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('flats.edit', $flat) }}" data-size="max-w-3xl" aria-label="Edit {{ $flat->flat_no }}"><x-icon name="edit" size="h-4 w-4" /></button>
            @endcan
            @can('delete', $flat)
                <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-row-action="delete" data-url="{{ route('flats.destroy', $flat) }}" data-confirm="Delete unit {{ $flat->flat_no }}?" data-confirm-text="Delete" aria-label="Delete {{ $flat->flat_no }}"><x-icon name="trash" size="h-4 w-4" /></button>
            @endcan
        </td>
    </tr>
@empty
    <tr><td colspan="{{ $showProject ? 8 : 7 }}"><x-empty-state icon="door" title="No units found" /></td></tr>
@endforelse
