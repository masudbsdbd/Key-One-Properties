<form method="POST" action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($project->exists) @method('PUT') @endif

    <x-form.input name="name" label="Project Name" :value="$project->name" required autofocus />
    <x-form.input name="code" label="Code" :value="$project->code" placeholder="{{ $project->exists ? '' : 'Auto' }}" />
    <x-form.input name="location_text" label="Location" :value="$project->location_text" icon="map-pin" required />
    <x-form.select name="status" label="Status" :options="$statuses" :selected="$project->status" required />
    <x-form.input name="address" label="Address" :value="$project->address" wrapper-class="sm:col-span-2" />
    <x-form.input name="start_date" type="date" label="Start Date" :value="$project->start_date?->toDateString()" required />
    <x-form.input name="target_completion_date" type="date" label="Target Completion" :value="$project->target_completion_date?->toDateString()" />
    <x-form.input name="actual_completion_date" type="date" label="Actual Completion" :value="$project->actual_completion_date?->toDateString()" />
    <x-form.input name="estimated_budget" label="Estimated Budget (BDT)" :value="$project->estimated_budget" inputmode="decimal" />
    <x-form.input name="latitude" label="Latitude" :value="$project->latitude" inputmode="decimal" />
    <x-form.input name="longitude" label="Longitude" :value="$project->longitude" inputmode="decimal" />
    <x-form.textarea name="description" label="Description" :value="$project->description" rows="3" wrapper-class="sm:col-span-2" />

    @php
        $defaultPackages = $project->exists ? 0 : array_sum(array_map('count', App\Models\Project::defaultWorkPackages()));
    @endphp
    @if ($defaultPackages)
        <div class="sm:col-span-2">
            <input type="hidden" name="default_work_packages" value="0">
            <x-form.checkbox name="default_work_packages" label="Add {{ $defaultPackages }} default work packages" :checked="true" />
        </div>
    @endif

    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $project->exists ? 'Save' : 'Create Project' }}</x-button>
    </x-form.actions>
</form>
