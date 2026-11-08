<x-layouts.app title="Search">
    <x-page-header :title="$term !== '' ? 'Results for “'.$term.'”' : 'Search'" :breadcrumbs="['Dashboard' => route('dashboard'), 'Search' => null]" />

    @php($total = collect($results)->sum(fn ($items) => $items->count()))
    @if ($total === 0)
        <x-card :padded="false"><x-empty-state icon="search" :title="mb_strlen($term) < 2 ? 'Type at least 2 characters' : 'No results'" /></x-card>
    @else
        <div class="grid gap-6 xl:grid-cols-3">
            @if ($results['projects']->isNotEmpty())
                <x-card title="Projects" :padded="false">
                    <ul class="divide-y divide-line">
                        @foreach ($results['projects'] as $project)
                            <li><a href="{{ route('projects.show', $project) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-surface-muted">
                                <span class="icon-tile icon-tile-sm tone-accent"><x-icon name="building" size="h-4 w-4" /></span>
                                <span class="min-w-0"><span class="block truncate font-medium text-ink-900">{{ $project->name }}</span><span class="block text-[13px] text-ink-500">{{ $project->code }} · {{ $project->location_text }}</span></span>
                            </a></li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
            @if ($results['flats']->isNotEmpty())
                <x-card title="Units" :padded="false">
                    <ul class="divide-y divide-line">
                        @foreach ($results['flats'] as $flat)
                            <li><a href="{{ route('flats.show', $flat) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-surface-muted">
                                <span class="icon-tile icon-tile-sm tone-orange"><x-icon name="door" size="h-4 w-4" /></span>
                                <span class="min-w-0 flex-1"><span class="block font-medium text-ink-900">{{ $flat->flat_no }}</span><span class="block truncate text-[13px] text-ink-500">{{ $flat->project->name }} · {{ $flat->building->code }}</span></span>
                                <x-badge :tone="App\Models\Flat::STATUS_TONES[$flat->status] ?? 'neutral'">{{ App\Models\Flat::STATUSES[$flat->status] ?? $flat->status }}</x-badge>
                            </a></li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
            @if ($results['customers']->isNotEmpty())
                <x-card title="Customers" :padded="false">
                    <ul class="divide-y divide-line">
                        @foreach ($results['customers'] as $customer)
                            <li><a href="{{ route('customers.show', $customer) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-surface-muted">
                                <x-avatar :name="$customer->full_name" />
                                <span class="min-w-0"><span class="block truncate font-medium text-ink-900">{{ $customer->full_name }}</span><span class="block text-[13px] text-ink-500">{{ $customer->customer_code }} · {{ App\Support\Phone::display($customer->primary_mobile) }}</span></span>
                            </a></li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    @endif
</x-layouts.app>
