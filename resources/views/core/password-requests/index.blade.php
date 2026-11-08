<x-layouts.app title="Password Requests">
    <x-page-header title="Password Requests" :breadcrumbs="['Dashboard' => route('dashboard'), 'Users' => route('users.index'), 'Password Requests' => null]" />

    <x-data-table :url="route('password-requests.table')" sort="requested" dir="desc" search="Search name, email or mobile" :columns="[
        ['label' => 'User'],
        ['label' => 'Mobile'],
        ['label' => 'Requested', 'sort' => 'requested'],
        ['label' => 'IP'],
        ['label' => 'Status'],
        ['label' => '', 'class' => 'w-56'],
    ]">
        <x-slot:filters>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status">
                @foreach (App\Models\PasswordResetRequest::STATUSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
