@props(['name' => '', 'size' => 'h-9 w-9 text-xs'])
@php
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $tones = ['tone-accent', 'tone-violet', 'tone-orange', 'tone-info', 'tone-warning'];
    $tone = $tones[abs(crc32($name)) % count($tones)];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center justify-center rounded-full font-semibold shrink-0 $tone $size"]) }} aria-hidden="true">{{ $initials ?: '?' }}</span>
