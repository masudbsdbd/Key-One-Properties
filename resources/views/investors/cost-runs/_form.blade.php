@php $today = now(config('app.display_timezone'))->toDateString(); @endphp
<form method="POST" action="{{ route('cost-runs.store') }}" data-ajax-form class="space-y-5">
    @csrf
    <x-form.select name="project" label="Project" :url="route('options.projects')" :options="$project ? [$project->public_id => $project->name] : []" :selected="$project?->public_id" required placeholder="Search projects" />
    <x-form.input name="cost_cutoff" type="date" label="Cost Up To" :value="$today" :max="$today" required />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Calculate</x-button>
    </x-form.actions>
</form>
