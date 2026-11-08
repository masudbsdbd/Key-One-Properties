<form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}" data-ajax-form class="space-y-5" data-permission-form>
    @csrf
    @if ($role->exists) @method('PUT') @endif

    <div class="grid gap-5 sm:grid-cols-2">
        <x-form.input name="name" label="Role Name" :value="$role->name" required autofocus />
        <x-form.input name="description" label="Description" :value="$role->description" />
    </div>

    <div data-field>
        <div class="mb-2 flex items-center justify-between">
            <p class="form-label mb-0">Permissions</p>
            <label class="inline-flex items-center gap-2 text-sm text-ink-600"><input type="checkbox" class="form-check" data-check-all> Select all</label>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($groups as $group => $permissions)
                <fieldset class="rounded-card border border-line p-4" data-permission-group>
                    <legend class="sr-only">{{ $group }}</legend>
                    <label class="mb-3 flex items-center gap-2.5 border-b border-line pb-3 text-sm font-semibold text-ink-900">
                        <input type="checkbox" class="form-check" data-check-group> {{ $group }}
                    </label>
                    <div class="space-y-2.5">
                        @foreach ($permissions as $name => $label)
                            <label class="flex items-center gap-2.5 text-sm {{ $grantable($name) ? 'text-ink-700' : 'text-ink-400' }}">
                                <input type="checkbox" name="permissions[]" value="{{ $name }}" class="form-check" @checked(in_array($name, $granted, true)) @disabled(! $grantable($name)) @unless ($grantable($name)) data-disabled-reason="You can only grant permissions you have" @endunless>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>
        <p class="form-error" data-error hidden></p>
    </div>

    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $role->exists ? 'Save' : 'Create Role' }}</x-button>
    </x-form.actions>
</form>
