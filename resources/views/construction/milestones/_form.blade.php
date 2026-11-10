<form method="POST" action="{{ $milestone->exists ? route('construction.milestones.update', [$project, $milestone]) : route('construction.milestones.store', $project) }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($milestone->exists) @method('PUT') @endif
    <x-form.input name="title" label="Title" :value="$milestone->title" required autofocus wrapper-class="sm:col-span-2" />
    <x-form.input name="planned_at" type="date" label="Planned Date" :value="$milestone->planned_at?->toDateString()" required />
    <x-form.select name="work_package" label="Work Package" :options="$packages" :selected="$milestone->workPackage?->public_id" placeholder="None" />
    <x-form.select name="status" label="Status" :options="App\Models\Milestone::STATUSES" :selected="$milestone->status" required />
    <x-form.input name="completed_at" type="date" label="Completed On" :value="$milestone->completed_at?->toDateString()" />
    @if ($milestone->exists)
        <x-form.input name="reschedule_reason" label="Reason for Date Change" maxlength="255" wrapper-class="sm:col-span-2" />
    @endif
    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save</x-button>
    </x-form.actions>
</form>
