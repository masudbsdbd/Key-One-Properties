<x-layouts.app :title="'Unit '.$flat->flat_no">
    <x-page-header :title="'Unit '.$flat->flat_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Units' => route('flats.index'), $flat->project->name => route('projects.show', $flat->project).'#flats', $flat->flat_no => null]">
        <x-slot:actions>
            @can('changePrice', $flat)
                <x-button variant="secondary" icon="tag" data-remote-modal="{{ route('flats.price.edit', $flat) }}" data-size="max-w-md">Change Price</x-button>
            @endcan
            @if (! $flat->isLockedBySales())
                @can('changeStatus', $flat)
                    <x-button variant="secondary" icon="refresh" data-remote-modal="{{ route('flats.status.edit', $flat) }}" data-size="max-w-md">Change Status</x-button>
                @endcan
            @endif
            @can('update', $flat)
                <x-button icon="edit" data-remote-modal="{{ route('flats.edit', $flat) }}" data-size="max-w-3xl">Edit</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Status" :value="App\Models\Flat::STATUSES[$flat->status]" icon="door" />
        <x-stat-card label="List Price" :value="App\Support\Format::money($flat->list_price, false)" icon="tag" />
        <x-stat-card label="Saleable Area" :value="number_format((float) $flat->saleable_area, 0).' '.$flat->area_unit" icon="grid" />
        <x-stat-card label="Rate" :value="$flat->list_price && (float) $flat->saleable_area > 0 ? App\Support\Format::money((float) $flat->list_price / (float) $flat->saleable_area, false).' / '.$flat->area_unit : '—'" icon="bar-chart" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-card title="Details" class="xl:col-span-2">
            <dl class="grid gap-5 sm:grid-cols-3">
                <div><dt class="detail-label">Project</dt><dd class="detail-value"><a href="{{ route('projects.show', $flat->project) }}" class="text-accent-700 hover:underline">{{ $flat->project->name }}</a></dd></div>
                <div><dt class="detail-label">Building</dt><dd class="detail-value">{{ $flat->building->name }} ({{ $flat->building->code }})</dd></div>
                <div><dt class="detail-label">Floor</dt><dd class="detail-value">{{ $flat->floor->label }}</dd></div>
                <div><dt class="detail-label">Type</dt><dd class="detail-value">{{ $flat->flat_type ?: '—' }}</dd></div>
                <div><dt class="detail-label">Bed / Bath / Balcony</dt><dd class="detail-value">{{ $flat->bedrooms ?? '—' }} / {{ $flat->bathrooms ?? '—' }} / {{ $flat->balconies ?? '—' }}</dd></div>
                <div><dt class="detail-label">Facing</dt><dd class="detail-value">{{ $flat->facing ?: '—' }}</dd></div>
                <div><dt class="detail-label">Created By</dt><dd class="detail-value">{{ $flat->creator?->name ?? '—' }}</dd></div>
                <div><dt class="detail-label">Created</dt><dd class="detail-value">{{ App\Support\Format::datetime($flat->created_at) }}</dd></div>
                @if ($flat->description)
                    <div class="sm:col-span-3"><dt class="detail-label">Description</dt><dd class="detail-value whitespace-pre-line">{{ $flat->description }}</dd></div>
                @endif
            </dl>
        </x-card>
        <x-card title="Sales">
            <x-empty-state icon="calendar" title="Coming in Phase 3" class="py-6" />
        </x-card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-card title="Price History" :padded="false">
            <div data-partial-url="{{ route('flats.history', [$flat, 'type' => 'price']) }}">
                @include('flats.partials._price-history', ['history' => $flat->priceHistory])
            </div>
        </x-card>
        <x-card title="Status History" :padded="false">
            <div data-partial-url="{{ route('flats.history', [$flat, 'type' => 'status']) }}">
                @include('flats.partials._status-history', ['history' => $flat->statusHistory])
            </div>
        </x-card>
    </div>

    <x-card title="Images" class="mt-6">
        @can('manageMedia', $flat)
            <x-slot:actions>
                <form method="POST" action="{{ route('flats.media.store', $flat) }}" enctype="multipart/form-data" data-ajax-form data-reset data-refresh-partials>
                    @csrf
                    <label class="btn btn-primary btn-sm cursor-pointer"><x-icon name="upload" size="h-4 w-4" />Upload<input type="file" name="images[]" accept="image/png,image/jpeg,image/webp" multiple class="sr-only" data-auto-submit></label>
                    <span class="form-error mt-0" data-form-error hidden></span>
                </form>
            </x-slot:actions>
        @endcan
        <div data-partial-url="{{ route('flats.media.index', $flat) }}">
            @include('flats.partials._media')
        </div>
    </x-card>

    <x-card title="Activity" class="mt-6" :padded="false">
        @include('projects.partials._activity', ['entries' => $activity])
    </x-card>
</x-layouts.app>
