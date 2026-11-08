@props(['title' => null, 'padded' => true])
<section {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($actions))
        <header class="card-header">
            <h2 class="card-title">{{ $title }}</h2>
            @isset($actions)<div class="flex items-center gap-2">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div @class(['card-body' => $padded])>{{ $slot }}</div>
</section>
