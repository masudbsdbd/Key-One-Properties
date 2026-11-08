@props(['label', 'value', 'icon' => 'bar-chart', 'tone' => 'accent', 'href' => null])
<div {{ $attributes->merge(['class' => 'card relative overflow-hidden p-5 flex items-center gap-4']) }}>
    <svg class="pointer-events-none absolute -right-2 bottom-0 h-full w-32 opacity-[0.14]" viewBox="0 0 120 90" preserveAspectRatio="xMaxYMax meet" aria-hidden="true">
        <g fill="none" stroke="var(--brand-primary)" stroke-width="1.4">
            <path d="M14 90V48l22-10v52M36 90V24l28-14v80M64 90V40l22 8v42M86 90V30l26 10v50"/>
            <path d="M42 30h16M42 42h16M42 54h16M42 66h16M42 78h16M92 44h14M92 56h14M92 68h14M92 80h14"/>
        </g>
    </svg>
    <span class="icon-tile tone-{{ $tone }} relative"><x-icon :name="$icon" size="h-7 w-7" /></span>
    <div class="relative min-w-0">
        <p class="text-[15px] text-ink-700">{{ $label }}</p>
        <p @class(['mt-0.5 font-semibold text-ink-900 tabular-nums whitespace-nowrap', 'text-[28px] leading-8' => mb_strlen((string) $value) <= 10, 'text-[21px] leading-8' => mb_strlen((string) $value) > 10])>{{ $value }}</p>
        @isset($meta)<div class="mt-1 text-[13px] font-medium text-success-600">{{ $meta }}</div>@endisset
    </div>
    @if ($href)
        <a href="{{ $href }}" class="relative ml-auto btn btn-ghost btn-icon" aria-label="{{ $label }}"><x-icon name="arrow-right" size="h-4 w-4" /></a>
    @endif
</div>
