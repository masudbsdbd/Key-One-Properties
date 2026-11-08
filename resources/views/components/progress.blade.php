@props(['value' => 0, 'size' => 'h-2', 'label' => true])
@php($pct = max(0, min(100, (float) $value)))
<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <div class="flex-1 overflow-hidden rounded-full bg-ink-900/[0.07] {{ $size }}" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
        <div class="h-full rounded-full {{ $pct >= 100 ? 'bg-success-600' : 'bg-accent-500' }}" style="width: {{ $pct }}%"></div>
    </div>
    @if ($label)<span class="w-12 text-right text-[13px] font-medium tabular-nums text-ink-700">{{ App\Support\Format::percent($pct) }}</span>@endif
</div>
