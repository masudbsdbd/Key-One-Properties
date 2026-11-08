<x-card title="Buildings" :padded="false">
    @can('manageBuildings', [App\Models\Flat::class, $project])
        <x-slot:actions>
            <x-button size="sm" variant="secondary" icon="plus" data-remote-modal="{{ route('projects.buildings.create', $project) }}">Add Building</x-button>
        </x-slot:actions>
    @endcan
    <div data-partial-url="{{ route('projects.buildings.index', $project) }}" data-lazy>
        <div class="space-y-3 p-5"><div class="skeleton h-12 w-full"></div></div>
    </div>
</x-card>
