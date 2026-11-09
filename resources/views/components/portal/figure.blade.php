@props(['label', 'value', 'state' => null, 'meta' => null])
@php
    $states = [
        'estimated' => ['Estimated', 'neutral'],
        'calculated' => ['Calculated', 'neutral'],
        'pending' => ['Pending Approval', 'warning'],
        'approved' => ['Approved Payable', 'info'],
        'paid' => ['Paid', 'success'],
    ];
    [$chip, $tone] = $states[$state] ?? [null, null];
@endphp
<div {{ $attributes->merge(['class' => 'card p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm text-ink-600">{{ $label }}</p>
        @if ($chip)<x-badge :tone="$tone" class="shrink-0">{{ $chip }}</x-badge>@endif
    </div>
    <p class="mt-2 text-2xl font-semibold tabular-nums text-ink-900">{{ $value }}</p>
    @if ($meta)<p class="mt-1 text-[13px] text-ink-500">{{ $meta }}</p>@endif
</div>
