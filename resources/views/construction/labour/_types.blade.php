@php($canManage = auth()->user()->can('construction.manage'))
<section class="card">
    <header class="flex items-center justify-between gap-3 px-5 pt-5">
        <h2 class="card-title">Labour Types</h2>
        <x-badge>{{ $types->count() }}</x-badge>
    </header>
    @if ($canManage)
        <form method="POST" action="{{ route('labour.types.store') }}" data-ajax-form data-reset data-refresh-partials class="mx-5 mt-4 grid items-start gap-2 rounded-card border border-dashed border-line-strong bg-surface-muted/60 p-3 sm:grid-cols-[1fr_180px_auto]">
            @csrf
            <x-form.input name="name" id="labour-type-name" placeholder="Name" aria-label="Name" maxlength="60" />
            <x-form.input name="default_rate" id="labour-type-rate" placeholder="Daily rate" aria-label="Daily rate" inputmode="decimal" />
            <x-button type="submit" icon="plus">Add</x-button>
        </form>
    @endif
    <ul class="mt-4 divide-y divide-line border-t border-line">
        @forelse ($types as $type)
            <li @class(['flex items-center gap-3 px-5 py-3 hover:bg-surface-muted/60', 'opacity-60' => ! $type->is_active])>
                <span class="icon-tile icon-tile-sm tone-info"><x-icon name="users" size="h-4 w-4" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-ink-900">{{ $type->name }}@unless ($type->is_active)<x-badge class="ml-2">Inactive</x-badge>@endunless</span>
                    <span class="block text-[12px] text-ink-500">{{ $type->default_rate !== null ? App\Support\Format::money($type->default_rate).' / day' : 'No default rate' }}</span>
                </span>
                <span @class(['shrink-0 rounded-full px-2.5 py-0.5 text-[12px] font-medium', 'bg-accent-50 text-accent-700' => $type->entries_count, 'bg-ink-900/5 text-ink-500' => ! $type->entries_count])>{{ \Illuminate\Support\Str::plural('entry', $type->entries_count, true) }}</span>
                @if ($canManage)
                    <span class="flex shrink-0 items-center">
                        <button type="button" class="btn btn-ghost btn-icon h-8 w-8" data-remote-modal="{{ route('labour.types.edit', $type) }}" data-size="max-w-md" aria-label="Edit {{ $type->name }}"><x-icon name="edit" size="h-4 w-4" /></button>
                        <button type="button" class="btn btn-ghost btn-icon h-8 w-8 text-danger-600" data-action="delete" data-url="{{ route('labour.types.destroy', $type) }}" data-confirm="Delete {{ $type->name }}?" data-confirm-text="Delete"
                            aria-label="Delete {{ $type->name }}" @disabled($type->entries_count) data-disabled-reason="Used by {{ \Illuminate\Support\Str::plural('entry', $type->entries_count, true) }}; mark it inactive instead"><x-icon name="trash" size="h-4 w-4" /></button>
                    </span>
                @endif
            </li>
        @empty
            <li><x-empty-state icon="users" title="No labour types" class="py-10" /></li>
        @endforelse
    </ul>
</section>
