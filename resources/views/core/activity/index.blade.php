<x-layouts.app title="Activity Log">
    <x-page-header title="Activity Log" :breadcrumbs="['Dashboard' => route('dashboard'), 'Activity Log' => null]" />

    <x-data-table :url="route('activity.table')" sort="time" dir="desc" :search="false" :columns="[
        ['label' => 'Time', 'sort' => 'time'],
        ['label' => 'User'],
        ['label' => 'Module'],
        ['label' => 'Event'],
        ['label' => 'Record'],
        ['label' => '', 'class' => 'w-16'],
    ]">
        <x-slot:filters>
            <select name="module" class="form-control w-40" data-table-filter aria-label="Module">
                <option value="">All modules</option>
                @foreach ($modules as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
            @can('users.view')
                <div class="w-56">
                    <select name="user" data-table-filter data-select data-select-url="{{ route('options.users') }}" data-placeholder="All users" class="form-control" aria-label="User"><option value=""></option></select>
                </div>
            @endcan
            <input type="date" name="from" class="form-control w-40" data-table-filter aria-label="From">
            <input type="date" name="to" class="form-control w-40" data-table-filter aria-label="To">
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
