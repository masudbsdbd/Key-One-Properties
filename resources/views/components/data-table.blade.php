@props([
    'url',
    'columns' => [],
    'search' => 'Search',
    'sort' => null,
    'dir' => 'asc',
    'viewKey' => null,
    'view' => 'table',
    'summary' => false,
    'selectable' => false,
])
{{--
    columns: [['label' => 'Name', 'sort' => 'name', 'class' => '']] ; a column without 'sort' is static.
    viewKey: preference key that enables the Table / Cards switch; the endpoint receives view=table|cards.
--}}
@php
    $cards = $viewKey && $view === 'cards';
    // Three or more filters get a row of their own so the bar never wraps into a ragged block.
    $stacked = isset($filters) && substr_count($filters->toHtml(), '<select') >= 3;
@endphp
<div {{ $attributes->merge(['class' => 'card overflow-hidden']) }} data-ajax-table data-url="{{ $url }}" @if ($viewKey) data-view-key="{{ $viewKey }}" data-view-url="{{ route('profile.preferences') }}" @endif @if ($sort) data-default-sort="{{ $sort }}" data-default-dir="{{ $dir }}" @endif>
    @if ($search !== false || isset($filters) || isset($toolbar) || $viewKey)
    <div @class(['border-b border-line p-4', 'space-y-3' => $stacked, 'flex flex-col gap-3 md:flex-row md:items-center' => ! $stacked])>
        @if ($stacked)<div class="flex flex-col gap-3 md:flex-row md:items-center">@endif
        @if ($search !== false)
            <label @class(['relative block', 'md:w-96' => $stacked, 'md:w-72' => ! $stacked])>
                <span class="sr-only">{{ $search }}</span>
                <x-icon name="search" size="h-4 w-4" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-400" />
                <input type="search" name="search" data-table-search class="form-control pl-9" placeholder="{{ $search }}" autocomplete="off">
            </label>
        @endif
        @if (isset($filters) && ! $stacked)<div class="flex flex-1 flex-wrap items-center gap-2">{{ $filters }}</div>@endif
        @if ($viewKey || isset($toolbar))
            <div class="flex items-center gap-2 md:ml-auto">
                {{ $toolbar ?? '' }}
                @if ($viewKey)
                    <input type="hidden" name="view" value="{{ $cards ? 'cards' : 'table' }}" data-table-filter data-table-view-input>
                    <div class="inline-flex rounded-control border border-line bg-surface p-0.5" role="group" aria-label="View">
                        @foreach (['table' => ['Table', 'list'], 'cards' => ['Cards', 'grid']] as $mode => [$label, $icon])
                            <button type="button" @class(['view-switch', 'is-active' => ($cards ? 'cards' : 'table') === $mode]) data-table-view="{{ $mode }}" aria-pressed="{{ ($cards ? 'cards' : 'table') === $mode ? 'true' : 'false' }}"><x-icon :name="$icon" size="h-4 w-4" />{{ $label }}</button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
        @if ($stacked)</div><div class="filter-row">{{ $filters }}</div>@endif
    </div>
    @endif
    @if ($selectable && isset($bulk))
        <div class="flex flex-wrap items-center gap-3 border-b border-line bg-accent-50/60 px-4 py-2.5" data-table-bulk hidden>
            <span class="text-sm font-semibold text-ink-900" data-bulk-count></span>
            <button type="button" class="text-[13px] font-medium text-accent-700 hover:underline" data-bulk-all></button>
            <button type="button" class="text-[13px] font-medium text-ink-500 hover:underline" data-bulk-clear>Clear</button>
            <div class="flex flex-wrap items-center gap-2 sm:ml-auto">{{ $bulk }}</div>
        </div>
    @endif
    @isset($stats)<div class="border-b border-line p-4" data-table-stats>{{ $stats }}</div>@endisset
    @if ($summary && ! isset($stats))
        <div class="border-b border-line p-4" data-table-stats><div class="grid grid-cols-2 gap-3 lg:grid-cols-4">@for ($i = 0; $i < 4; $i++)<div class="h-[66px] animate-pulse rounded-card bg-surface-muted"></div>@endfor</div></div>
    @endif
    @if ($viewKey)
        <div class="grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4" data-table-cards aria-live="polite" @unless ($cards) hidden @endunless></div>
    @endif
    <div class="overflow-x-auto" data-table-grid @if ($cards) hidden @endif>
        <table class="data-table">
            <thead>
                <tr>
                    @if ($selectable)<th scope="col" class="w-10"><input type="checkbox" class="form-check" data-select-page aria-label="Select all on this page"></th>@endif
                    @foreach ($columns as $column)
                        <th scope="col" class="{{ $column['class'] ?? '' }}" @isset($column['sort']) aria-sort="{{ $sort === $column['sort'] ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' }}" @endisset>
                            @isset($column['sort'])
                                <button type="button" class="sort-button" data-sort="{{ $column['sort'] }}">{{ $column['label'] }}<x-icon name="chevrons-up-down" size="h-3.5 w-3.5" /></button>
                            @else
                                {{ $column['label'] }}
                            @endisset
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody data-table-body aria-live="polite">
                <x-skeleton-rows :cols="count($columns) + ($selectable ? 1 : 0)" />
            </tbody>
        </table>
    </div>
    <div class="flex flex-col items-center justify-between gap-3 border-t border-line px-4 py-3 sm:flex-row">
        <p class="text-[13px] text-ink-500" data-table-summary></p>
        <nav class="flex flex-wrap items-center gap-1" data-table-pagination aria-label="Pagination"></nav>
    </div>
</div>
