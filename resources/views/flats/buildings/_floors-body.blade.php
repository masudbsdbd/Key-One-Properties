@php($canManage = auth()->user()->can('manageBuildings', [App\Models\Flat::class, $project]))
    @if ($canManage)
        <form method="POST" action="{{ route('projects.buildings.floors.store', [$project, $building]) }}" data-ajax-form data-keep-open="true" data-refresh-partials class="mb-5 grid grid-cols-[1fr_1fr_auto] items-end gap-3">
            @csrf
            <x-form.input name="floor_from" id="floors-from" type="number" label="From" min="-10" max="200" required />
            <x-form.input name="floor_to" id="floors-to" type="number" label="To" min="-10" max="200" required />
            <x-button type="submit" icon="plus" class="mb-0.5">Add</x-button>
        </form>
    @endif

    @if ($floors->isEmpty())
        <x-empty-state icon="stairs" title="No floors yet" />
    @else
        <ul class="divide-y divide-line rounded-card border border-line">
            @foreach ($floors->sortByDesc('floor_number') as $floor)
                <li class="flex items-center gap-3 px-4 py-2.5">
                    <span class="w-10 text-sm font-semibold tabular-nums text-ink-500">{{ $floor->floor_number }}</span>
                    @if ($canManage)
                        <form method="POST" action="{{ route('projects.buildings.floors.update', [$project, $building, $floor]) }}" data-ajax-form data-keep-open="true" class="flex flex-1 items-center gap-2">
                            @csrf @method('PUT')
                            <div class="flex-1" data-field>
                                <input name="label" value="{{ $floor->label }}" class="form-control h-9" aria-label="Label for floor {{ $floor->floor_number }}" required>
                                <p class="form-error" data-error hidden></p>
                            </div>
                            <button type="submit" class="btn btn-ghost btn-icon" aria-label="Save label"><x-icon name="check" size="h-4 w-4" /></button>
                        </form>
                    @else
                        <span class="flex-1 text-sm text-ink-900">{{ $floor->label }}</span>
                    @endif
                    <span class="text-[13px] text-ink-500">{{ $floor->flats_count }} units</span>
                    @if ($canManage && ! $floor->flats_count)
                        <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-action="delete" data-url="{{ route('projects.buildings.floors.destroy', [$project, $building, $floor]) }}" data-keep-open aria-label="Delete floor {{ $floor->label }}"><x-icon name="trash" size="h-4 w-4" /></button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
