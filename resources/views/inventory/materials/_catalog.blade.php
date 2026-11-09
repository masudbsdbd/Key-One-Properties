@php
    $canManage = auth()->user()->can('materials.manage');
    $lists = [
        'category' => ['Categories', $categories, 'tag', 'tone-accent', 'materials.categories', 120],
        'unit' => ['Units', $units, 'layers', 'tone-info', 'materials.units', 60],
    ];
@endphp
<div class="grid gap-6 lg:grid-cols-2">
    @foreach ($lists as $kind => [$title, $items, $icon, $tone, $route, $max])
        <section class="card min-w-0">
            <header class="flex items-center justify-between gap-3 px-5 pt-5">
                <h2 class="card-title">{{ $title }}</h2>
                <x-badge>{{ $items->count() }}</x-badge>
            </header>
            @if ($canManage)
                <form method="POST" action="{{ route($route.'.store') }}" data-ajax-form data-reset data-refresh-partials class="mx-5 mt-4 grid grid-cols-[110px_1fr_auto] items-start gap-2 rounded-card border border-dashed border-line-strong bg-surface-muted/60 p-3">
                    @csrf
                    <x-form.input name="code" id="{{ $kind }}-code" placeholder="Code" aria-label="Code" maxlength="20" />
                    <x-form.input name="name" id="{{ $kind }}-name" placeholder="Name" aria-label="Name" :maxlength="$max" />
                    <x-button type="submit" icon="plus">Add</x-button>
                </form>
            @endif
            <ul class="mt-4 divide-y divide-line border-t border-line">
                @forelse ($items as $item)
                    @php $label = $kind === 'category' ? $item->name : $item->code; @endphp
                    <li class="flex items-center gap-3 px-5 py-3 hover:bg-surface-muted/60">
                        <span class="icon-tile icon-tile-sm {{ $tone }}"><x-icon :name="$icon" size="h-4 w-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink-900">{{ $item->name }}</span>
                            <span class="mt-0.5 flex flex-wrap items-center gap-2 text-[12px] text-ink-500">
                                <span class="rounded bg-ink-900/5 px-1.5 py-px font-mono text-[11px] font-medium text-ink-700">{{ $item->code }}</span>
                                @if ($kind === 'unit')<span>{{ (int) $item->precision === 0 ? 'Whole numbers' : $item->precision.' decimal '.\Illuminate\Support\Str::plural('place', (int) $item->precision) }}</span>@endif
                            </span>
                        </span>
                        <span @class(['shrink-0 rounded-full px-2.5 py-0.5 text-[12px] font-medium', 'bg-accent-50 text-accent-700' => $item->materials_count, 'bg-ink-900/5 text-ink-500' => ! $item->materials_count])>{{ \Illuminate\Support\Str::plural('material', $item->materials_count, true) }}</span>
                        @if ($canManage)
                            <span class="flex shrink-0 items-center">
                                <button type="button" class="btn btn-ghost btn-icon h-8 w-8" data-remote-modal="{{ route($route.'.edit', $item) }}" data-size="max-w-md" aria-label="Edit {{ $label }}"><x-icon name="edit" size="h-4 w-4" /></button>
                                <button type="button" class="btn btn-ghost btn-icon h-8 w-8 text-danger-600" data-action="delete" data-url="{{ route($route.'.destroy', $item) }}" data-confirm="Delete {{ $label }}?" data-confirm-text="Delete"
                                    aria-label="Delete {{ $label }}" @disabled($item->materials_count) data-disabled-reason="Used by {{ \Illuminate\Support\Str::plural('material', $item->materials_count, true) }}"><x-icon name="trash" size="h-4 w-4" /></button>
                            </span>
                        @endif
                    </li>
                @empty
                    <li><x-empty-state :icon="$icon" :title="'No '.strtolower($title)" class="py-10" /></li>
                @endforelse
            </ul>
        </section>
    @endforeach
</div>
