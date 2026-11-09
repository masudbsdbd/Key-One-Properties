@props(['status', 'labels' => []])
@php
    $tones = [
        'draft' => 'neutral', 'pending' => 'warning', 'submitted' => 'info', 'approved' => 'success', 'posted' => 'success',
        'issued' => 'info', 'in_progress' => 'info', 'partially_received' => 'warning', 'partially_returned' => 'warning',
        'partially_converted' => 'warning', 'received' => 'violet', 'returned' => 'violet', 'converted' => 'violet',
        'paid' => 'violet', 'completed' => 'violet', 'billed' => 'violet', 'closed' => 'neutral', 'rejected' => 'danger',
        'cancelled' => 'neutral', 'reversed' => 'danger', 'active' => 'success', 'inactive' => 'neutral', 'on_hold' => 'warning',
        'not_started' => 'neutral', 'published' => 'success', 'confirmed' => 'success', 'converted_to_sale' => 'violet',
    ];
    $label = $labels[$status] ?? Illuminate\Support\Str::headline($status);
@endphp
<x-badge :tone="$tones[$status] ?? 'neutral'" {{ $attributes }}>{{ $label }}</x-badge>
