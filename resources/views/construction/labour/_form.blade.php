@php
    $types = App\Models\LabourType::query()->active()->orderBy('name')->get();
    $rates = $types->mapWithKeys(fn ($t) => [$t->code => $t->default_rate !== null ? rtrim(rtrim($t->default_rate, '0'), '.') : null]);
    $grid = 'sm:grid-cols-[1fr_96px_96px_120px_120px_36px]';
@endphp
<form method="POST" action="{{ route('labour.store') }}" data-ajax-form class="space-y-5" data-labour-form data-labour-rates='@json($rates)'>
    @csrf
    <div class="grid gap-5 sm:grid-cols-3">
        <x-form.select name="project" id="labour-project" label="Project" :url="route('options.projects')" :options="$project ? [$project->public_id => $project->name.' ('.$project->code.')'] : []" :selected="$project?->public_id" required placeholder="Search projects" wrapper-class="sm:col-span-2" />
        <x-form.input name="work_date" type="date" label="Work Date" :value="now(config('app.display_timezone'))->toDateString()" :max="now(config('app.display_timezone'))->toDateString()" required />
        <x-form.select name="work_package" label="Work Package" :url="route('options.work-packages')" depends-on="#labour-project" depends-param="project" placeholder="None" wrapper-class="sm:col-span-3" />
    </div>

    <div class="space-y-3">
        <div class="hidden gap-3 text-[13px] font-medium text-ink-600 sm:grid {{ $grid }}">
            <span>Labour Type</span><span>Headcount</span><span>Days</span><span>Rate per Day</span><span class="text-right">Amount</span><span></span>
        </div>
        <div class="space-y-3" data-labour-rows></div>
        <template data-labour-template>
            <div class="grid items-start gap-3 rounded-card border border-line p-3 sm:border-0 sm:p-0 {{ $grid }}" data-labour-row>
                <div data-field>
                    <select name="rows[__I__][type]" class="form-control" aria-label="Labour type">
                        <option value="">Select type</option>
                        @foreach ($types as $type)<option value="{{ $type->code }}">{{ $type->name }}</option>@endforeach
                    </select>
                    <p class="form-error" data-error hidden></p>
                </div>
                <div data-field><input name="rows[__I__][headcount]" type="number" min="1" class="form-control" placeholder="0" aria-label="Headcount" data-labour-qty><p class="form-error" data-error hidden></p></div>
                <div data-field><input name="rows[__I__][units]" class="form-control" inputmode="decimal" value="1" aria-label="Days" data-labour-qty><p class="form-error" data-error hidden></p></div>
                <div data-field><input name="rows[__I__][rate]" class="form-control" inputmode="decimal" placeholder="0.00" aria-label="Rate per day" data-labour-qty><p class="form-error" data-error hidden></p></div>
                <p class="flex h-10 items-center justify-end text-sm font-semibold tabular-nums text-ink-900" data-labour-amount>৳ 0</p>
                <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-labour-remove aria-label="Remove row" data-disabled-reason="At least one row is needed"><x-icon name="x" size="h-4 w-4" /></button>
            </div>
        </template>
        <div class="flex items-center justify-between gap-3">
            <button type="button" class="btn btn-sm btn-secondary" data-labour-add><x-icon name="plus" size="h-4 w-4" />Add Row</button>
            <p class="text-sm text-ink-600"><span data-labour-count></span> Total <span class="ml-1 text-lg font-semibold tabular-nums text-ink-900" data-calc-total>৳ 0</span></p>
        </div>
        <p class="form-error" data-field-error="rows" hidden></p>
    </div>

    <x-form.textarea name="notes" label="Notes" rows="2" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save</x-button>
    </x-form.actions>
</form>
