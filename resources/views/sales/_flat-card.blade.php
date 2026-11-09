@php($holder = $flat->claim?->claimant)
<div class="rounded-card border border-line bg-surface-muted p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="font-semibold text-ink-900">Unit {{ $flat->flat_no }} · {{ $flat->flat_type }}</p>
            <p class="text-[13px] text-ink-500">{{ $flat->project->name }} · {{ $flat->building->name }} · {{ $flat->floor->label }} · {{ number_format((float) $flat->saleable_area) }} {{ $flat->area_unit }}</p>
        </div>
        <div class="text-right">
            <p class="text-[13px] text-ink-500">Asking Price</p>
            <p class="text-lg font-semibold text-ink-900" data-asking-price="{{ $flat->list_price }}"><x-money :value="$flat->list_price" /></p>
        </div>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-2 text-[13px]">
        <x-status-badge :status="$flat->status" :labels="App\Models\Flat::STATUSES" />
        @if ($holder)<span class="text-danger-600">Held by {{ $holder->booking_no ?? $holder->sale_no }}</span>@endif
        @foreach ($flat->priceHistory as $h)
            <span class="text-ink-500">· {{ App\Support\Format::date($h->effective_at) }} <x-money :value="$h->new_price" :decimals="false" /></span>
        @endforeach
    </div>
</div>
