<form method="POST" action="{{ route('projects.progress.store', $project) }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    <x-form.select name="stage" label="Stage" :options="$stages->mapWithKeys(fn ($s) => [$s->public_id => $s->name.' ('.App\Support\Format::percent($s->progress_percent).')'])" :selected="$selected" required wrapper-class="sm:col-span-2" />
    <x-form.input name="progress_percent" type="number" label="Progress (%)" min="0" max="100" step="0.5" required />
    <x-form.input name="report_date" type="date" label="Report Date" :value="now(config('app.display_timezone'))->toDateString()" :max="now(config('app.display_timezone'))->toDateString()" required />
    <x-form.select name="visibility" label="Visibility" :options="App\Models\ProjectProgressUpdate::VISIBILITIES" selected="internal" required wrapper-class="sm:col-span-2" />
    <x-form.textarea name="summary" label="Summary" rows="3" required wrapper-class="sm:col-span-2" />

    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Record</x-button>
    </x-form.actions>
</form>
