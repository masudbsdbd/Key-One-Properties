@php($canManage = auth()->user()->can('materials.manage'))
<div class="grid gap-6 lg:grid-cols-2">
    <x-card title="Categories" :padded="false">
        @if ($canManage)
            <form method="POST" action="{{ route('materials.categories.store') }}" data-ajax-form data-reset data-refresh-partials class="grid grid-cols-[120px_1fr_auto] items-start gap-3 border-b border-line p-5">
                @csrf
                <x-form.input name="code" id="category-code" placeholder="Code" aria-label="Code" />
                <x-form.input name="name" id="category-name" placeholder="Name" aria-label="Name" />
                <x-button type="submit" icon="plus">Add</x-button>
            </form>
        @endif
        <ul class="divide-y divide-line">
            @forelse ($categories as $category)
                <li class="flex items-center justify-between px-5 py-3 text-sm"><span><span class="font-medium text-ink-900">{{ $category->name }}</span> <span class="text-ink-500">{{ $category->code }}</span></span><span class="text-ink-500">{{ $category->materials_count }}</span></li>
            @empty
                <li><x-empty-state icon="list" title="No categories" /></li>
            @endforelse
        </ul>
    </x-card>
    <x-card title="Units" :padded="false">
        @if ($canManage)
            <form method="POST" action="{{ route('materials.units.store') }}" data-ajax-form data-reset data-refresh-partials class="grid grid-cols-[120px_1fr_auto] items-start gap-3 border-b border-line p-5">
                @csrf
                <x-form.input name="code" id="unit-code" placeholder="Code" aria-label="Code" />
                <x-form.input name="name" id="unit-name" placeholder="Name" aria-label="Name" />
                <x-button type="submit" icon="plus">Add</x-button>
            </form>
        @endif
        <ul class="divide-y divide-line">
            @forelse ($units as $unit)
                <li class="flex items-center justify-between px-5 py-3 text-sm"><span><span class="font-medium text-ink-900">{{ $unit->code }}</span> <span class="text-ink-500">{{ $unit->name }}</span></span><span class="text-ink-500">{{ $unit->materials_count }}</span></li>
            @empty
                <li><x-empty-state icon="list" title="No units" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
