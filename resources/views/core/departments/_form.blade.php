<form method="POST" action="{{ $department->exists ? route('departments.update', $department) : route('departments.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($department->exists) @method('PUT') @endif

    <x-form.input name="name" label="Name" :value="$department->name" required autofocus />
    <x-form.select name="status" label="Status" :options="App\Models\Department::STATUSES" :selected="$department->status" required />
    <x-form.input name="description" label="Description" :value="$department->description" wrapper-class="sm:col-span-2" />
    <x-form.select name="members" label="Members" :options="$members" :selected="$members->keys()->all()" :url="route('options.users')" multiple placeholder="Search users" wrapper-class="sm:col-span-2" data-min-input="0" />

    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $department->exists ? 'Save' : 'Create Department' }}</x-button>
    </x-form.actions>
</form>
