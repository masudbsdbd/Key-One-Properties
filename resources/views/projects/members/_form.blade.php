<form method="POST" action="{{ route('projects.members.store', $project) }}" data-ajax-form class="space-y-5">
    @csrf
    <x-form.select name="users" label="Users" :url="route('options.users')" multiple required placeholder="Search users" />
    <x-form.input name="membership_role" label="Project Role" placeholder="Site Engineer" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Add</x-button>
    </x-form.actions>
</form>
