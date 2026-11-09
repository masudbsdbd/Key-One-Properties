@props(['to' => null, 'href' => null])
@php $href ??= App\Support\RecordLink::url($to); @endphp
@if ($href)<a href="{{ $href }}" {{ $attributes->merge(['class' => 'ref-link']) }}>{{ $slot }}</a>@else<span {{ $attributes }}>{{ $slot }}</span>@endif
