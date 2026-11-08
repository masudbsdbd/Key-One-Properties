<x-layouts.app title="Labour">
    <x-page-header title="Labour" :breadcrumbs="['Dashboard' => route('dashboard'), 'Construction' => route('construction.index'), 'Labour' => null]">
        @can('construction.labour.manage')
            <x-slot:actions>
                <x-button icon="plus" data-remote-modal="{{ route('labour.create') }}" data-size="max-w-3xl" data-auto-create>Record Labour</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>
    @include('construction.labour._table', ['project' => null])
</x-layouts.app>
