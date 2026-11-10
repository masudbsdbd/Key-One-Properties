@php($actor = auth()->user())
@php($selectable = $actor->can('flats.change_status'))
@forelse ($flats as $flat)
    <article class="unit-card">
        <svg class="pointer-events-none absolute right-3 top-9 h-14 w-20 opacity-[0.12]" viewBox="0 0 120 90" aria-hidden="true">
            <g fill="none" stroke="var(--brand-primary)" stroke-width="1.6"><path d="M14 90V48l22-10v52M36 90V24l28-14v80M64 90V40l22 8v42M86 90V30l26 10v50"/></g>
        </svg>
        <div class="relative flex items-start justify-between gap-3">
            <div class="min-w-0">
                <a href="{{ route('flats.show', $flat) }}" class="block text-2xl font-semibold leading-7 text-ink-900 hover:text-accent-700">{{ $flat->flat_no }}</a>
                <p class="truncate text-sm text-ink-600"><x-ref :to="$flat->project">{{ $flat->project->name }}</x-ref></p>
                @if ($flat->deal)<p class="truncate text-[13px] text-ink-600"><x-ref :to="$flat->deal">{{ $flat->deal->customer?->full_name }}</x-ref></p>@endif
            </div>
            <span class="flex shrink-0 items-center gap-2">
                <x-badge :tone="App\Models\Flat::STATUS_TONES[$flat->status] ?? 'neutral'">{{ App\Models\Flat::STATUSES[$flat->status] ?? $flat->status }}</x-badge>
                @if ($selectable)<input type="checkbox" class="form-check" value="{{ $flat->public_id }}" data-select-row aria-label="Select {{ $flat->flat_no }}">@endif
            </span>
        </div>
        <dl class="relative mt-4 grid grid-cols-2 gap-x-3 gap-y-2.5 text-sm text-ink-700">
            <div class="col-span-2 flex items-center gap-2"><x-icon name="layers" size="h-4 w-4" class="shrink-0 text-ink-500" /><dt class="sr-only">Floor</dt><dd class="truncate">{{ $flat->building->code }} · {{ $flat->floor->label }}</dd></div>
            @if ($flat->bedrooms !== null)
                <div class="flex items-center gap-2"><x-icon name="bed" size="h-4 w-4" class="shrink-0 text-ink-500" /><dt class="sr-only">Bedrooms</dt><dd>{{ $flat->bedrooms }} Bed</dd></div>
                <div class="flex items-center gap-2"><x-icon name="bath" size="h-4 w-4" class="shrink-0 text-ink-500" /><dt class="sr-only">Bathrooms</dt><dd>{{ $flat->bathrooms ?? 0 }} Bath</dd></div>
            @else
                <div class="col-span-2 flex items-center gap-2"><x-icon name="door" size="h-4 w-4" class="shrink-0 text-ink-500" /><dt class="sr-only">Type</dt><dd>{{ $flat->flat_type ?: '—' }}</dd></div>
            @endif
            <div class="flex items-center gap-2"><x-icon name="grid" size="h-4 w-4" class="shrink-0 text-ink-500" /><dt class="sr-only">Area</dt><dd class="tabular-nums">{{ number_format((float) $flat->saleable_area) }} {{ $flat->area_unit }}</dd></div>
            <div class="flex items-center gap-2"><x-icon name="tag" size="h-4 w-4" class="shrink-0 text-ink-500" /><dt class="sr-only">Price</dt><dd class="tabular-nums">{{ App\Support\Format::money($flat->list_price, false) }}</dd></div>
        </dl>
        <div class="relative mt-4 flex items-center gap-2 border-t border-line pt-3">
            <x-button size="sm" variant="secondary" icon="eye" href="{{ route('flats.show', $flat) }}" class="flex-1 justify-center">View</x-button>
            @can('update', $flat)
                <x-button size="sm" variant="secondary" icon="edit" data-remote-modal="{{ route('flats.edit', $flat) }}" data-size="max-w-3xl" class="flex-1 justify-center">Edit</x-button>
            @endcan
            @can('changePrice', $flat)
                <button type="button" class="btn btn-secondary btn-sm btn-icon" data-remote-modal="{{ route('flats.price.edit', $flat) }}" data-size="max-w-md" aria-label="Change price of {{ $flat->flat_no }}"><x-icon name="tag" size="h-4 w-4" /></button>
            @endcan
            @if (! $flat->isLockedBySales() && $actor->can('changeStatus', $flat))
                <button type="button" class="btn btn-secondary btn-sm btn-icon" data-remote-modal="{{ route('flats.status.edit', $flat) }}" data-size="max-w-md" aria-label="Change status of {{ $flat->flat_no }}"><x-icon name="refresh" size="h-4 w-4" /></button>
            @endif
            @can('delete', $flat)
                <button type="button" class="btn btn-secondary btn-sm btn-icon text-danger-600" data-row-action="delete" data-url="{{ route('flats.destroy', $flat) }}" data-confirm="Delete unit {{ $flat->flat_no }}?" data-confirm-text="Delete" aria-label="Delete {{ $flat->flat_no }}"><x-icon name="trash" size="h-4 w-4" /></button>
            @endcan
        </div>
    </article>
@empty
    <div class="sm:col-span-2 xl:col-span-3 2xl:col-span-4"><x-empty-state icon="door" title="No units found" /></div>
@endforelse
