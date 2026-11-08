<x-layouts.app title="Users">
    <x-page-header title="Users" :breadcrumbs="['Dashboard' => route('dashboard'), 'Users' => null]">
        <x-slot:actions>
            @can('users.reset_password')
                @php($pending = App\Models\PasswordResetRequest::query()->where('status', 'pending')->count())
                <x-button variant="secondary" icon="key" href="{{ route('password-requests.index') }}">Password Requests @if ($pending)<span class="nav-count ml-1">{{ $pending }}</span>@endif</x-button>
            @endcan
            @can('create', App\Models\User::class)
                <x-button icon="user-plus" data-remote-modal="{{ route('users.create') }}" data-auto-create>New User</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table :url="route('users.table')" sort="name" search="Search name, email or mobile" :columns="[
        ['label' => 'Name', 'sort' => 'name'],
        ['label' => 'Mobile'],
        ['label' => 'Role'],
        ['label' => 'Departments'],
        ['label' => 'Status'],
        ['label' => 'Last sign-in', 'sort' => 'last_login'],
        ['label' => '', 'class' => 'w-32'],
    ]">
        <x-slot:filters>
            <div class="w-48">
                <select name="role" class="form-control" data-table-filter data-select data-placeholder="All roles" aria-label="Role">
                    <option value=""></option>
                    @foreach ($roles as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
            </div>
            <select name="status" class="form-control w-36" data-table-filter aria-label="Status">
                <option value="">All statuses</option>
                @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
