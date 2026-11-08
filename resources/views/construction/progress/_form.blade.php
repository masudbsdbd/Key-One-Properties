<form method="POST" action="{{ route('construction.progress.store', $project) }}" data-ajax-form class="grid gap-5 sm:grid-cols-2" data-progress-form>
    @csrf
    <x-form.select name="work_package" label="Work Package" :options="$packages" required wrapper-class="sm:col-span-2" />
    <x-form.select name="milestone" label="Milestone" :options="$milestones" placeholder="None" />
    <x-form.input name="progress_percent" type="number" label="Progress (%)" min="0" max="100" step="0.5" required />
    <x-form.input name="report_date" type="date" label="Report Date" :value="now(config('app.display_timezone'))->toDateString()" :max="now(config('app.display_timezone'))->toDateString()" required />
    <x-form.select name="visibility" label="Visibility" :options="App\Models\ConstructionProgressUpdate::VISIBILITIES" selected="internal" required />
    <x-form.textarea name="notes" label="Notes" rows="3" required wrapper-class="sm:col-span-2" />
    <x-form.file name="evidence" label="Evidence" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple hint="Images or PDF, max 10 MB each" wrapper-class="sm:col-span-2" data-evidence-input />
    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Submit</x-button>
    </x-form.actions>
</form>
