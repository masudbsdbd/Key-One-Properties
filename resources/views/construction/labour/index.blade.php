<x-layouts.app title="Labour">
    <x-page-header title="Labour" :breadcrumbs="['Dashboard' => route('dashboard'), 'Construction' => route('construction.index'), 'Labour' => null]">
        @can('construction.labour.manage')
            <x-slot:actions>
                <x-button icon="plus" data-remote-modal="{{ route('labour.create') }}" data-size="max-w-3xl" data-auto-create>Record Labour</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        <button type="button" role="tab" class="tab" id="tab-entries" data-tab="entries" aria-controls="panel-entries">Entries</button>
        <button type="button" role="tab" class="tab" id="tab-types" data-tab="types" aria-controls="panel-types">Labour Types</button>
    </div>
    <section id="panel-entries" role="tabpanel" aria-labelledby="tab-entries">
        @include('construction.labour._table', ['project' => null])
    </section>
    <section id="panel-types" role="tabpanel" aria-labelledby="tab-types" hidden>
        <div class="max-w-3xl" data-partial-url="{{ route('labour.types.index') }}" data-lazy><div class="card p-5"><div class="skeleton h-24 w-full"></div></div></div>
    </section>
</x-layouts.app>
