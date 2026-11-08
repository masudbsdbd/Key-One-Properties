@props([
    'url',
    'columns' => [],
    'search' => 'Search',
    'sort' => null,
    'dir' => 'asc',
])
{{--
    columns: [['label' => 'Name', 'sort' => 'name', 'class' => '']] ; a column without 'sort' is static.
--}}
<div {{ $attributes->merge(['class' => 'card overflow-hidden']) }} data-ajax-table data-url="{{ $url }}" @if ($sort) data-sort="{{ $sort }}" data-dir="{{ $dir }}" @endif>
    <div class="flex flex-col gap-3 border-b border-line p-4 md:flex-row md:items-center">
        @if ($search !== false)
            <label class="relative block md:w-72">
                <span class="sr-only">{{ $search }}</span>
                <x-icon name="search" size="h-4 w-4" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-400" />
                <input type="search" name="search" data-table-search class="form-control pl-9" placeholder="{{ $search }}" autocomplete="off">
            </label>
        @endif
        @isset($filters)<div class="flex flex-1 flex-wrap items-center gap-2">{{ $filters }}</div>@endisset
        @isset($toolbar)<div class="flex items-center gap-2 md:ml-auto">{{ $toolbar }}</div>@endisset
    </div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
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
                <x-skeleton-rows :cols="count($columns)" />
            </tbody>
        </table>
    </div>
    <div class="flex flex-col items-center justify-between gap-3 border-t border-line px-4 py-3 sm:flex-row">
        <p class="text-[13px] text-ink-500" data-table-summary></p>
        <nav class="flex flex-wrap items-center gap-1" data-table-pagination aria-label="Pagination"></nav>
    </div>
</div>
