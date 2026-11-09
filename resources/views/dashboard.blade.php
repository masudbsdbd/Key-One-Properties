<x-layouts.app title="Dashboard">
    <x-page-header :title="$greeting.', '.\Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first()" />

    @if ($stats)
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($stats as $card)
                <x-stat-card :label="$card['label']" :value="$card['value']" :icon="$card['icon']" :href="$card['href']" />
            @endforeach
        </div>
    @endif

    @if ($approvals)
        <x-card title="Awaiting You" :padded="false" class="mt-6 max-w-3xl">
            <ul class="divide-y divide-line">
                @foreach ($approvals as $item)
                    <li>
                        <a href="{{ $item['href'] }}" class="flex items-center gap-3 px-5 py-3 hover:bg-surface-muted">
                            <span class="icon-tile icon-tile-sm tone-accent"><x-icon :name="$item['icon']" size="h-4 w-4" /></span>
                            <span class="flex-1 text-sm font-medium text-ink-900">{{ $item['label'] }}</span>
                            <x-badge tone="warning">{{ $item['count'] }}</x-badge>
                            <x-icon name="chevron-right" size="h-4 w-4 text-ink-400" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if (! $stats && ! $approvals)
        <x-card :padded="false">
            <x-empty-state icon="home" title="Nothing to show yet" />
        </x-card>
    @endif
</x-layouts.app>
