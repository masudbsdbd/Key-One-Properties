<form method="POST" action="{{ route('labour.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-3" data-labour-form>
    @csrf
    <x-form.select name="project" id="labour-project" label="Project" :url="route('options.projects')" :options="$project ? [$project->public_id => $project->name.' ('.$project->code.')'] : []" :selected="$project?->public_id" required placeholder="Search projects" wrapper-class="sm:col-span-2" />
    <x-form.input name="work_date" type="date" label="Work Date" :value="now(config('app.display_timezone'))->toDateString()" :max="now(config('app.display_timezone'))->toDateString()" required />
    <x-form.select name="work_package" label="Work Package" :url="route('options.work-packages')" depends-on="#labour-project" depends-param="project" placeholder="None" wrapper-class="sm:col-span-2" />
    <x-form.select name="labour_type" label="Labour Type" :options="App\Models\LabourEntry::TYPES" required />
    <x-form.input name="headcount" type="number" label="Headcount" min="1" value="1" required data-calc />
    <x-form.input name="units" label="Days" value="1" inputmode="decimal" required data-calc />
    <x-form.input name="rate" label="Rate per Day (BDT)" inputmode="decimal" required data-calc />
    <div class="sm:col-span-3 flex items-center justify-between rounded-card bg-surface-muted px-4 py-3">
        <span class="text-sm text-ink-500">Total</span>
        <span class="text-lg font-semibold tabular-nums text-ink-900" data-calc-total>৳ 0.00</span>
    </div>
    <x-form.textarea name="notes" label="Notes" rows="2" wrapper-class="sm:col-span-3" />
    <x-form.actions class="sm:col-span-3 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save</x-button>
    </x-form.actions>
</form>
