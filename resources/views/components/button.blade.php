@props(['variant' => 'primary', 'size' => null, 'icon' => null, 'href' => null, 'type' => 'button'])
@php
    $classes = 'btn btn-'.$variant.($size ? ' btn-'.$size : '');
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" size="h-4 w-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        <span class="spinner btn-spinner" aria-hidden="true"></span>
        @if ($icon)<x-icon :name="$icon" size="h-4 w-4" />@endif
        {{ $slot }}
    </button>
@endif
