<x-layouts.app title="Bookings">
    <x-page-header title="Bookings" :breadcrumbs="['Dashboard' => route('dashboard'), 'Sales' => route('sales.overview'), 'Bookings' => null]">
        @can('sales.booking.create')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('bookings.create') }}" data-size="max-w-4xl" data-auto-create>New Booking</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <x-data-table :url="route('bookings.table')" sort="date" dir="desc" search="Search booking, unit or customer" :columns="[
        ['label' => 'Booking', 'sort' => 'date'], ['label' => 'Unit'], ['label' => 'Customer'], ['label' => 'Agreed Price', 'class' => 'text-right', 'sort' => 'price'], ['label' => 'Booking Money', 'class' => 'text-right'], ['label' => 'Status'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-44" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\FlatBooking::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            <div class="w-56"><select name="project" class="form-control" data-table-filter data-select data-select-url="{{ route('options.projects') }}" data-placeholder="All projects" aria-label="Project"><option value=""></option></select></div>
        </x-slot:filters>
        <x-slot:toolbar>@can('sales.export')<x-export-menu :url="route('bookings.export')" />@endcan</x-slot:toolbar>
    </x-data-table>
</x-layouts.app>
