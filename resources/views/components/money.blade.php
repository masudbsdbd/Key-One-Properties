@props(['value', 'decimals' => true, 'muted' => false])
<span {{ $attributes->merge(['class' => 'tabular-nums whitespace-nowrap'.($muted ? ' text-ink-500' : '')]) }}>{{ App\Support\Format::money($value, $decimals) }}</span>
