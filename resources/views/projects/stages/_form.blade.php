<form method="POST" action="{{ $stage->exists ? route('projects.stages.update', [$project, $stage]) : route('projects.stages.store', $project) }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($stage->exists) @method('PUT') @endif

    <x-form.input name="name" label="Stage Name" :value="$stage->name" required autofocus />
    <x-form.select name="status" label="Status" :options="App\Models\ProjectStage::STATUSES" :selected="$stage->status" required />
    <x-form.input name="planned_start_at" type="date" label="Planned Start" :value="$stage->planned_start_at?->toDateString()" />
    <x-form.input name="planned_end_at" type="date" label="Planned End" :value="$stage->planned_end_at?->toDateString()" />
    <x-form.input name="actual_start_at" type="date" label="Actual Start" :value="$stage->actual_start_at?->toDateString()" />
    <x-form.input name="actual_end_at" type="date" label="Actual End" :value="$stage->actual_end_at?->toDateString()" />
    <x-form.textarea name="remarks" label="Remarks" :value="$stage->remarks" rows="2" wrapper-class="sm:col-span-2" />

    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $stage->exists ? 'Save' : 'Add Stage' }}</x-button>
    </x-form.actions>
</form>
