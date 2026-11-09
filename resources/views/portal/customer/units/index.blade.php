<x-layouts.portal title="My Units" portal="customer">
    <x-page-header title="My Units" :breadcrumbs="['Overview' => route('customer.dashboard'), 'My Units' => null]" />
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($units as $entry)
            @include('portal.customer._unit-card')
        @empty
            <x-empty-state icon="door" title="No units" />
        @endforelse
    </div>
</x-layouts.portal>
