<form method="POST" action="{{ route('stock.issue') }}" data-ajax-form class="space-y-5">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    <div class="grid gap-5 sm:grid-cols-3">
        <x-form.select name="warehouse" id="issue-warehouse" label="From Warehouse" :url="route('options.warehouses')" required placeholder="Search warehouses" />
        <x-form.select name="project" id="issue-project" label="Project" :url="route('options.projects')" required placeholder="Search projects" />
        <x-form.input name="issued_at" type="date" label="Date" :value="now(config('app.display_timezone'))->toDateString()" :max="now(config('app.display_timezone'))->toDateString()" required />
        <x-form.select name="work_package" label="Work Package" :url="route('options.work-packages')" depends-on="#issue-project" depends-param="project" placeholder="None" />
        <x-form.input name="purpose" label="Purpose" wrapper-class="sm:col-span-2" />
    </div>
    <x-line-items available-url="{{ route('stock.available') }}" warehouse-selector="#issue-warehouse" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Issue</x-button>
    </x-form.actions>
</form>
