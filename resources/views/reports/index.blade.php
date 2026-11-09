<x-layouts.app title="Reports">
    <x-page-header title="Reports" :breadcrumbs="['Dashboard' => route('dashboard'), 'Reports' => null]">
        <x-slot:actions>@can('reports.export')<x-button variant="secondary" icon="download" href="{{ route('report-exports.index') }}">My Exports</x-button>@endcan @can('dashboard.executive.view')<x-button variant="secondary" icon="bar-chart" href="{{ route('executive.index') }}">Executive Dashboard</x-button>@endcan</x-slot:actions>
    </x-page-header>
    @if (! $catalog)
        <x-card><x-empty-state icon="file" title="No reports available" /></x-card>
    @endif
    <div class="grid gap-6 lg:grid-cols-2 2xl:grid-cols-3">
        @foreach ($catalog as $group => $reports)
            <x-card :title="App\Modules\Reports\BaseReport::GROUPS[$group]" :padded="false">
                @foreach ($reports as $r)
                    <a href="{{ route('reports.show', $r->key()) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0 hover:bg-surface-muted">
                        <span class="icon-tile icon-tile-sm tone-accent"><x-icon name="file" size="h-4 w-4" /></span>
                        <span class="min-w-0 flex-1"><span class="block font-medium text-ink-900">{{ $r->name() }}</span><span class="block truncate text-[13px] text-ink-500">{{ $r->basis() }}</span></span>
                        <x-icon name="chevron-right" size="h-4 w-4 text-ink-400" />
                    </a>
                @endforeach
            </x-card>
        @endforeach
    </div>
</x-layouts.app>
