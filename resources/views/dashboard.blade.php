<x-layouts.app title="Dashboard">
    <x-page-header :title="$greeting.', '.\Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first()" />

    @if ($stats)
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($stats as $card)
                <x-stat-card :label="$card['label']" :value="$card['value']" :icon="$card['icon']" :href="$card['href']" />
            @endforeach
        </div>
    @else
        <x-card :padded="false">
            <x-empty-state icon="home" title="Nothing to show yet" />
        </x-card>
    @endif
</x-layouts.app>
