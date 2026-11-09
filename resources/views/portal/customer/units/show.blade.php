@php
    $money = fn ($v) => App\Support\Format::money($v);
    $flat = $unit->flat;
@endphp
<x-layouts.portal :title="'Unit '.$flat->flat_no" portal="customer">
    <x-page-header :title="$unit->project->name.' · Unit '.$flat->flat_no" :breadcrumbs="['Overview' => route('customer.dashboard'), 'My Units' => route('customer.flats.index'), 'Unit '.$flat->flat_no => null]">
        <x-slot:actions>@if ($isSale && $canInstallments)<x-button icon="calendar" href="{{ route('customer.installments.index', ['unit' => $unit->public_id]) }}">Installments</x-button>@endif</x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="$isSale ? 'Contract' : 'Agreed Price'" :value="$money($position['contract'])" icon="file" tone="info" />
        <x-stat-card label="Paid" :value="$money($position['paid'])" icon="check" tone="success" />
        <x-stat-card :label="App\Support\Money::cmp($position['balance'], '0') < 0 ? 'Advance' : 'Remaining'" :value="$money(ltrim($position['balance'], '-'))" icon="tag" tone="accent" />
        <x-stat-card label="Overdue" :value="$money($position['overdue'])" icon="alert" :tone="App\Support\Money::isPositive($position['overdue']) ? 'danger' : 'success'" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Unit" class="min-w-0 xl:col-span-2">
            <dl class="grid gap-5 sm:grid-cols-2">
                <div><dt class="detail-label">Project</dt><dd class="detail-value">{{ $unit->project->name }}</dd></div>
                <div><dt class="detail-label">Building / Floor</dt><dd class="detail-value">{{ $flat->building->name }} · {{ $flat->floor->label }}</dd></div>
                <div><dt class="detail-label">Saleable Area</dt><dd class="detail-value">{{ number_format((float) $flat->saleable_area) }} {{ $flat->area_unit }}</dd></div>
                <div><dt class="detail-label">Type</dt><dd class="detail-value">{{ collect([$flat->flat_type, $flat->bedrooms !== null && ! str_contains((string) $flat->flat_type, 'Bed') ? $flat->bedrooms.' Bed' : null, $flat->bathrooms !== null ? $flat->bathrooms.' Bath' : null])->filter()->join(' · ') ?: '—' }}</dd></div>
                <div><dt class="detail-label">{{ $isSale ? 'Sale' : 'Booking' }}</dt><dd class="detail-value">{{ $isSale ? $unit->sale_no : $unit->booking_no }} · {{ App\Support\Format::date($isSale ? $unit->agreement_date : $unit->booking_date) }}</dd></div>
                @if (App\Support\Money::isPositive($position['late_fees']))<div><dt class="detail-label">Late Fees Billed</dt><dd class="detail-value">{{ $money($position['late_fees']) }}</dd></div>@endif
                @if (App\Support\Money::isPositive($position['refunded']))<div><dt class="detail-label">Refunded</dt><dd class="detail-value">{{ $money($position['refunded']) }}</dd></div>@endif
            </dl>
        </x-card>
        @if ($isSale)
            <x-card title="Progress" :padded="false">
                <x-timeline :items="[
                    ['title' => 'Sale approved', 'meta' => App\Support\Format::date($unit->approved_at), 'tone' => 'success'],
                    ['title' => 'Registration', 'meta' => App\Models\FlatRegistration::STATUSES[$unit->registration?->status ?? 'pending'] ?? '—', 'tone' => $unit->registration?->status === 'registered' ? 'success' : 'neutral'],
                    ['title' => 'Handover', 'meta' => App\Models\FlatHandover::STATUSES[$unit->handover?->status ?? 'pending'] ?? '—', 'tone' => $unit->handover?->status === 'completed' ? 'success' : 'neutral'],
                ]" />
            </x-card>
        @endif
    </div>

    @if ($progressProject)
        <x-card title="Construction" class="mt-6">
            <x-slot:actions><x-button size="sm" variant="secondary" icon="activity" href="{{ route('customer.projects.show', $progressProject->public_id) }}">View Progress</x-button></x-slot:actions>
            <x-progress :value="$progressProject->overallProgress()" size="h-3" />
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($progressProject->stages as $stage)
                    <div class="rounded-control border border-line px-3 py-2.5">
                        <p class="truncate text-sm font-medium text-ink-900">{{ $stage->name }}</p>
                        <x-progress :value="$stage->progress_percent" class="mt-1.5" />
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif
</x-layouts.portal>
