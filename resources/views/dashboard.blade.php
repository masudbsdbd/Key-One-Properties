@php
    $titles = ['attention' => 'Needs Attention', 'projects' => 'Project Health', 'cashflow' => 'Money In and Out', 'forecast' => 'Collections Ahead',
        'aging' => 'Overdue by Age', 'units' => 'Unit Inventory', 'milestones' => 'Milestones', 'investors' => 'Investors'];
    $has = fn ($p) => in_array($p, $panels, true);
    $cols = [1 => '2xl:grid-cols-1', 2 => '2xl:grid-cols-2', 3 => '2xl:grid-cols-3', 4 => '2xl:grid-cols-4'];
@endphp
<x-layouts.app title="Dashboard">
    <x-page-header :title="$greeting.', '.\Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first()" />

    <div data-executive data-widget-base="{{ url('dashboard/panels') }}" class="space-y-6">
        @if (count($panels) > 1)
            <div class="flex flex-wrap items-center gap-3">
                <div class="w-full sm:w-64"><select name="project" class="form-control" data-exec-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option>@if ($project)<option value="{{ $project->public_id }}" selected>{{ $project->name }}</option>@endif</select></div>
                <x-badge>Operational figures · BDT</x-badge>
            </div>
        @endif

        @if ($has('kpis'))
            <div data-widget="kpis" aria-live="polite"><div data-widget-body><div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">@for ($i = 0; $i < 4; $i++)<div class="skeleton h-[104px]"></div>@endfor</div></div></div>
        @endif

        @php $wide = count($approvals) > 5; @endphp
        <div @class(['grid items-start gap-6', 'xl:grid-cols-3' => $approvals && ! $wide])>
            <x-card :title="$titles['attention']" data-widget="attention" aria-live="polite" @class(['min-w-0', 'xl:col-span-2' => $approvals && ! $wide])>
                <div data-widget-body><div class="skeleton h-40 w-full"></div></div>
            </x-card>
            @if ($approvals)
                <x-card title="Awaiting You" :padded="$wide" class="min-w-0">
                    <ul @class(['divide-y divide-line' => ! $wide, 'grid gap-2 sm:grid-cols-2 xl:grid-cols-3' => $wide])>
                        @foreach ($approvals as $item)
                            <li>
                                <a href="{{ $item['href'] }}" @class(['flex items-center gap-3 hover:bg-surface-muted', 'px-5 py-3' => ! $wide, 'rounded-control border border-line px-3 py-2' => $wide])>
                                    <span class="icon-tile icon-tile-sm tone-accent"><x-icon :name="$item['icon']" size="h-4 w-4" /></span>
                                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink-900">{{ $item['label'] }}</span>
                                    <x-badge tone="warning">{{ $item['count'] }}</x-badge>
                                    <x-icon name="chevron-right" size="h-4 w-4 text-ink-400" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        @if ($has('projects'))
            <x-card :title="$titles['projects']" :padded="false" data-widget="projects" aria-live="polite">
                <div data-widget-body><div class="skeleton m-5 h-40"></div></div>
            </x-card>
        @endif

        @if ($has('cashflow') || $has('forecast'))
            <div class="grid gap-6 xl:grid-cols-3">
                @if ($has('cashflow'))
                    <x-card :title="$titles['cashflow']" data-widget="cashflow" aria-live="polite" @class(['min-w-0', 'xl:col-span-2' => $has('forecast')])><div data-widget-body><div class="skeleton h-48 w-full"></div></div></x-card>
                @endif
                @if ($has('forecast'))
                    <x-card :title="$titles['forecast']" data-widget="forecast" aria-live="polite" class="min-w-0"><div data-widget-body><div class="skeleton h-48 w-full"></div></div></x-card>
                @endif
            </div>
        @endif

        @php $small = array_values(array_filter(['aging', 'units', 'investors', 'milestones'], $has)); @endphp
        @if ($small)
            <div class="grid gap-6 lg:grid-cols-2 {{ $cols[min(count($small), 4)] }}">
                @foreach ($small as $p)
                    <x-card :title="$titles[$p]" data-widget="{{ $p }}" aria-live="polite" class="min-w-0"><div data-widget-body><div class="skeleton h-40 w-full"></div></div></x-card>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
