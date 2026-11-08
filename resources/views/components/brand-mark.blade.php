@props(['size' => 'h-11 w-11'])
<svg {{ $attributes->merge(['class' => $size]) }} viewBox="0 0 48 48" fill="none" aria-hidden="true">
    <defs>
        <linearGradient id="brand-mark-g" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="color-mix(in oklab, var(--brand-primary) 55%, white)" />
            <stop offset="1" stop-color="var(--brand-primary)" />
        </linearGradient>
    </defs>
    <path d="M6 44V22l8-4v26M17 44V12l9-6v38M29 44V16l8 3v25M40 44V24l4 2v18" stroke="url(#brand-mark-g)" stroke-width="3" stroke-linejoin="round" />
    <path d="M3 44h42" stroke="url(#brand-mark-g)" stroke-width="3" stroke-linecap="round" />
</svg>
