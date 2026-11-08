<x-layouts.app title="Units">
    <x-page-header title="Units (Flats)" :breadcrumbs="['Dashboard' => route('dashboard'), 'Units' => null]">
        @can('flats.create')
            <x-slot:actions>
                <x-button icon="plus" data-remote-modal="{{ route('flats.create') }}" data-size="max-w-3xl" data-auto-create>New Unit</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    @include('flats.partials._table', ['project' => null])
</x-layouts.app>
