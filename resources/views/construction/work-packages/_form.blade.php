<form method="POST" action="{{ $package->exists ? route('construction.work-packages.update', [$project, $package]) : route('construction.work-packages.store', $project) }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($package->exists) @method('PUT') @endif
    <x-form.input name="name" label="Name" :value="$package->name" required autofocus />
    <x-form.input name="code" label="Code" :value="$package->code" placeholder="WP-01" required />
    <x-form.select name="stage" label="Project Stage" :options="$stages" :selected="$package->stage?->public_id" placeholder="Select stage" />
    <x-form.select name="parent" label="Parent Package" :options="$parents" :selected="$package->parent?->public_id" placeholder="None" />
    <x-form.select name="responsible" label="Responsible" :url="route('options.users')" :options="$package->responsible ? [$package->responsible->public_id => $package->responsible->name] : []" :selected="$package->responsible?->public_id" placeholder="Search users" />
    <x-form.input name="budget_amount" label="Budget (BDT)" :value="$package->budget_amount" inputmode="decimal" required />
    <x-form.input name="planned_start_at" type="date" label="Planned Start" :value="$package->planned_start_at?->toDateString()" />
    <x-form.input name="planned_end_at" type="date" label="Planned End" :value="$package->planned_end_at?->toDateString()" />
    <x-form.select name="status" label="Status" :options="App\Models\WorkPackage::STATUSES" :selected="$package->status" required />
    <x-form.textarea name="description" label="Description" :value="$package->description" rows="2" wrapper-class="sm:col-span-2" />
    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $package->exists ? 'Save' : 'Create' }}</x-button>
    </x-form.actions>
</form>
